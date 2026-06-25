<?php
namespace App\Jobs;

use App\Models\Order;
use App\Models\PancakeShop;
use App\Services\PancakeApiService;
use App\Support\DemographicsExtractor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Re-fetches orders whose status may have changed since they were first synced.
 *
 * Two modes:
 *   - Specific IDs (from webhook): pass $pancakeIds — only those orders are refreshed.
 *   - Open-order sweep (daily scheduler): leave $pancakeIds empty — all non-terminal
 *     orders in the DB are re-fetched from the API to pick up any status changes that
 *     the incremental sync missed (because Pancake's from_date filter is by creation
 *     date, not update date).
 */
class RefreshOpenOrdersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800; // 30 min — enough for ~5 K individual fetches
    public int $tries   = 1;

    /** @param string[] $pancakeIds Empty = sweep all open orders. */
    public function __construct(
        private readonly int   $shopModelId,
        private readonly array $pancakeIds = [],
    ) {}

    public function handle(): void
    {
        $shop = PancakeShop::findOrFail($this->shopModelId);

        // For the daily full sweep, prevent concurrent runs.
        $lock = null;
        if (empty($this->pancakeIds)) {
            $lock = Cache::lock("refresh_open_orders_lock_{$shop->id}", 1860);
            if (!$lock->get()) {
                Log::info("RefreshOpenOrdersJob: sweep already running for shop {$shop->shop_id}, skipping.");
                return;
            }
        }

        try {
            $api = PancakeApiService::forShop($shop);

            $ids = !empty($this->pancakeIds)
                ? $this->pancakeIds
                : $this->openOrderIds($shop->id);

            $updated = 0;
            $failed  = 0;

            foreach ($ids as $pancakeId) {
                $raw = $api->getOrderById((string) $pancakeId);

                if ($raw) {
                    $this->upsertOne($shop->id, $raw);
                    $updated++;
                } else {
                    $failed++;
                }

                usleep(100000); // 100 ms between requests — stay within rate limits
            }

            Log::info("RefreshOpenOrdersJob: shop {$shop->shop_id} — refreshed {$updated}, failed {$failed} / " . count($ids));

            if ($updated > 0) {
                Cache::put("shop_bust_{$shop->id}", time(), now()->addDays(30));
            }
        } finally {
            $lock?->release();
        }
    }

    /** IDs of every order that is not yet in a terminal state. */
    private function openOrderIds(int $shopId): array
    {
        return Order::where('shop_id', $shopId)
            ->where('is_returned', false)
            ->where('is_cancelled', false)
            ->where('status', '!=', 'delivered')
            ->pluck('pancake_id')
            ->toArray();
    }

    private function upsertOne(int $shopId, array $raw): void
    {
        $now    = now()->format('Y-m-d H:i:s');
        $status = (int) ($raw['status'] ?? -1);
        $addr   = $raw['shipping_address'] ?? [];
        $partner = $raw['partner'] ?? [];
        $cod    = (float) ($raw['cod']     ?? 0);
        $prepaid = (float) ($raw['prepaid'] ?? 0);

        $orderNote    = $raw['note'] ?? null;
        $consultParts = [];
        foreach ($raw['customer']['notes'] ?? [] as $n) {
            if (($n['order_id'] ?? null) === '' && !empty($n['message']) && empty($n['removed_at'])) {
                $consultParts[] = $n['message'];
            }
        }
        $combinedNote = trim(implode("\n", array_filter(array_merge([$orderNote], $consultParts)))) ?: null;

        $record = [
            'shop_id'             => $shopId,
            'pancake_id'          => (string) ($raw['id'] ?? ''),
            'order_code'          => (string) ($raw['id'] ?? ''),
            'customer_pancake_id' => (string) ($raw['customer']['id'] ?? ''),
            'customer_name'       => $raw['bill_full_name'] ?? null,
            'customer_phone'      => $raw['bill_phone_number'] ?? null,
            'status'              => $raw['status_name'] ?? (string) $status,
            'payment_method'      => $cod > 0 ? 'COD' : ($prepaid > 0 ? 'Prepaid' : 'Unknown'),
            'payment_status'      => null,
            'total_price'         => (float) ($raw['total_price'] ?? 0),
            'shipping_fee'        => (float) ($raw['shipping_fee'] ?? 0),
            'discount'            => (float) ($raw['total_discount'] ?? 0),
            'cod_amount'          => $cod,
            'courier'             => $partner['partner_name'] ?? null,
            'tracking_code'       => $partner['extend_code'] ?? null,
            'shipping_status'     => $partner['partner_status'] ?? null,
            'province'            => $addr['province_name'] ?? null,
            'district'            => $addr['district_name'] ?? null,
            'ward'                => $addr['commune_name'] ?? null,
            'shipping_address'    => $addr['full_address'] ?? null,
            'channel'             => $raw['order_sources_name'] ?? null,
            'warehouse'           => $raw['warehouse_info']['name'] ?? null,
            'is_rts'              => in_array($status, [4, 5]),
            'is_returned'         => $status === 5,
            'is_cancelled'        => $status === 6,
            'is_wholesale'        => (bool) ($raw['is_exchange_order'] ?? false),
            'extra_note'          => $combinedNote,
            'return_reason'       => $raw['returned_reason_name'] ?? null,
            'customer_age'        => DemographicsExtractor::age($combinedNote),
            'health_condition'    => ($c = DemographicsExtractor::conditions($combinedNote)) ? json_encode($c) : null,
            'items'               => isset($raw['items']) ? json_encode($raw['items']) : null,
            'utm_data'            => ($utm = array_filter([
                'source'   => $raw['p_utm_source']   ?? null,
                'medium'   => $raw['p_utm_medium']   ?? null,
                'campaign' => $raw['p_utm_campaign'] ?? null,
            ])) ? json_encode($utm) : null,
            'ordered_at'          => isset($raw['inserted_at']) ? date('Y-m-d H:i:s', strtotime($raw['inserted_at'])) : null,
            'delivered_at'        => null,
            'created_at'          => $now,
            'updated_at'          => $now,
        ];

        Order::upsert([$record], ['shop_id', 'pancake_id'], [
            'status', 'shipping_status', 'courier', 'tracking_code',
            'is_rts', 'is_returned', 'is_cancelled',
            'return_reason', 'total_price', 'cod_amount',
            'province', 'district', 'ward',
            'items', 'health_condition', 'updated_at',
        ]);
    }
}
