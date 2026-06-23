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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Lightweight 5-minute reconciliation job.
 *
 * Fetches the 3 most-recent pages from the Pancake API in parallel, covering:
 *   - Brand-new orders (paginated newest-first)
 *   - Recent status changes (delivered, returning, etc.)
 *
 * Runs frequently so the analytics dashboard stays accurate without waiting
 * for the hourly full sync to cycle back through old pages.
 */
class ReconcileOrdersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 240; // 4 min — must finish before the next 5-min tick
    public int $tries   = 1;

    private const PAGES = [1, 2, 3]; // newest 150 orders (3 × 50)

    public function __construct(private readonly int $shopModelId) {}

    public function handle(): void
    {
        $shop = PancakeShop::findOrFail($this->shopModelId);

        $lock = Cache::lock("reconcile_orders_lock_{$shop->id}", 260);
        if (!$lock->get()) {
            Log::info("ReconcileOrdersJob: already running for shop {$shop->shop_id}, skipping.");
            return;
        }

        try {
            $api     = PancakeApiService::forShop($shop);
            $results = $api->getOrderPagesBatch(self::PAGES);

            $fetched  = 0;
            $upserted = 0;

            foreach (self::PAGES as $page) {
                $batch = $results[$page];

                if ($batch === null) {
                    Log::warning("ReconcileOrdersJob: parallel fetch failed for page {$page}");
                    break; // abort this cycle; will retry on next 5-min tick
                }

                if (empty($batch)) break; // no more pages

                $upserted += $this->upsertBatch($shop->id, $batch);
                $fetched  += count($batch);

                if (count($batch) < PancakeApiService::PER_PAGE) break; // last page
            }

            if ($upserted > 0) {
                // Bust analytics cache so the next page load / AJAX refresh sees fresh data.
                Cache::put("shop_bust_{$shop->id}", time(), now()->addDays(30));
                Log::info("ReconcileOrdersJob: shop {$shop->shop_id} — fetched {$fetched}, upserted {$upserted}");
            }
        } finally {
            $lock->release();
        }
    }

    private function upsertBatch(int $shopId, array $batch): int
    {
        $now     = now()->format('Y-m-d H:i:s');
        $records = [];

        foreach ($batch as $raw) {
            if (empty($raw['id'])) continue;

            $status  = (int) ($raw['status'] ?? -1);
            $addr    = $raw['shipping_address'] ?? [];
            $partner = $raw['partner'] ?? [];
            $cod     = (float) ($raw['cod']     ?? 0);
            $prepaid = (float) ($raw['prepaid'] ?? 0);

            $records[] = [
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
                'extra_note'          => $raw['note'] ?? null,
                'return_reason'       => $raw['returned_reason_name'] ?? null,
                'customer_age'        => DemographicsExtractor::age($raw['note'] ?? null),
                'health_condition'    => ($c = DemographicsExtractor::conditions($raw['note'] ?? null)) ? json_encode($c) : null,
                'items'               => isset($raw['items'])    ? json_encode($raw['items'])    : null,
                'utm_data'            => ($utm = array_filter([
                    'source'   => $raw['p_utm_source']   ?? null,
                    'medium'   => $raw['p_utm_medium']   ?? null,
                    'campaign' => $raw['p_utm_campaign'] ?? null,
                ])) ? json_encode($utm) : null,
                'ordered_at'  => isset($raw['inserted_at']) ? date('Y-m-d H:i:s', strtotime($raw['inserted_at'])) : null,
                'delivered_at' => null,
                'created_at'  => $now,
                'updated_at'  => $now,
            ];
        }

        if (empty($records)) return 0;

        DB::transaction(function () use ($records) {
            Order::upsert($records, ['shop_id', 'pancake_id'], [
                'status', 'shipping_status', 'courier', 'tracking_code',
                'is_rts', 'is_returned', 'is_cancelled',
                'return_reason', 'total_price', 'cod_amount',
                'shipping_fee', 'discount',
                'province', 'district', 'ward',
                'customer_name', 'customer_phone',
                'extra_note', 'customer_age', 'health_condition',
                'items', 'utm_data', 'ordered_at', 'updated_at',
            ]);
        });

        return count($records);
    }
}
