<?php
namespace App\Jobs;

use App\Models\Order;
use App\Models\PancakeShop;
use App\Models\SyncLog;
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

class SyncOrdersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;
    public int $tries   = 1; // resumable — no point retrying; dispatcher re-queues

    private const PARALLEL_PAGES = 3; // pages fetched concurrently per batch

    public function __construct(
        private readonly int $shopModelId,
        private readonly ?string $fromDate = null,
    ) {}

    public function handle(): void
    {
        $shop = PancakeShop::findOrFail($this->shopModelId);

        // One sync per shop at a time — TTL slightly longer than job timeout.
        $lock = Cache::lock("sync_orders_lock_{$shop->id}", 3700);
        if (!$lock->get()) {
            Log::info("SyncOrdersJob: shop {$shop->shop_id} already syncing, skipping.");
            return;
        }

        $log           = null;
        $resumeKey     = 'sync_orders_page_' . $shop->id . '_' . md5((string) $this->fromDate);
        $startPage     = (int) Cache::get($resumeKey, 1);
        $totalFetched  = 0;
        $totalUpserted = 0;

        try {
            $log = SyncLog::create([
                'shop_id'    => $shop->id,
                'type'       => 'orders',
                'status'     => 'running',
                'started_at' => now(),
            ]);
            $api     = PancakeApiService::forShop($shop);
            $filters = $this->fromDate ? ['from_date' => $this->fromDate] : [];
            $page    = $startPage;

            while (true) {
                // Fetch PARALLEL_PAGES pages concurrently — fast initial state.
                $pages   = range($page, $page + self::PARALLEL_PAGES - 1);
                $results = $api->getOrderPagesBatch($pages, $filters);

                foreach ($pages as $p) {
                    $batch = $results[$p];

                    if ($batch === null) {
                        throw new \RuntimeException("Parallel fetch failed at page {$p} — will resume next run.");
                    }

                    if (empty($batch)) {
                        break 2; // no more pages
                    }

                    $upserted       = $this->upsertBatch($shop->id, $batch);
                    $totalFetched  += count($batch);
                    $totalUpserted += $upserted;

                    Cache::put($resumeKey, $p + 1, now()->addDays(7));

                    if (count($batch) < PancakeApiService::PER_PAGE) {
                        break 2; // last page of data
                    }
                }

                $page += self::PARALLEL_PAGES;
                usleep(300000); // 300 ms between parallel batches — respect rate limits
            }

            Cache::forget($resumeKey);
            $shop->update(['last_synced_at' => now()]);

            $log->update([
                'status'           => 'success',
                'records_fetched'  => $totalFetched,
                'records_upserted' => $totalUpserted,
                'finished_at'      => now(),
            ]);
        } catch (\Throwable $e) {
            $nextPage = (int) Cache::get($resumeKey, $startPage);
            Log::error(
                "SyncOrdersJob failed for shop {$shop->shop_id} " .
                "(fromDate={$this->fromDate}, next resume page={$nextPage}): {$e->getMessage()}"
            );
            $log?->update([
                'status'           => 'failed',
                'records_fetched'  => $totalFetched,
                'records_upserted' => $totalUpserted,
                'error_message'    => $e->getMessage(),
                'finished_at'      => now(),
            ]);
        } finally {
            $lock->release();
        }
    }

    private function upsertBatch(int $shopId, array $batch): int
    {
        $now     = now()->format('Y-m-d H:i:s');
        $records = [];

        foreach ($batch as $raw) {
            // Validate response — skip malformed records.
            if (empty($raw['id'])) continue;

            $r               = $this->mapOrder($shopId, $raw);
            $r['items']      = isset($r['items'])    ? json_encode($r['items'])    : null;
            $r['utm_data']   = isset($r['utm_data']) ? json_encode($r['utm_data']) : null;
            $r['created_at'] = $now;
            $r['updated_at'] = $now;
            $records[]       = $r;
        }

        if (empty($records)) return 0;

        // Transaction = rollback on failure so partial writes don't corrupt the dataset.
        DB::transaction(function () use ($records) {
            Order::upsert($records, ['shop_id', 'pancake_id'], [
                'order_code', 'customer_pancake_id', 'customer_name', 'customer_phone',
                'status', 'payment_method', 'payment_status', 'total_price', 'shipping_fee',
                'discount', 'cod_amount', 'courier', 'tracking_code', 'shipping_status',
                'province', 'district', 'ward', 'shipping_address', 'channel', 'warehouse',
                'is_rts', 'is_returned', 'is_cancelled', 'is_wholesale',
                'extra_note', 'return_reason', 'customer_age', 'health_condition',
                'items', 'utm_data', 'ordered_at', 'delivered_at',
                'updated_at',
            ]);
        });

        return count($records);
    }

    private function mapOrder(int $shopId, array $raw): array
    {
        $status  = (int) ($raw['status'] ?? -1);
        $addr    = $raw['shipping_address'] ?? [];
        $partner = $raw['partner'] ?? [];
        $cod     = (float) ($raw['cod']     ?? 0);
        $prepaid = (float) ($raw['prepaid'] ?? 0);

        return [
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
            'items'               => $raw['items'] ?? null,
            'utm_data'            => array_filter([
                'source'   => $raw['p_utm_source']   ?? null,
                'medium'   => $raw['p_utm_medium']   ?? null,
                'campaign' => $raw['p_utm_campaign'] ?? null,
            ]) ?: null,
            'ordered_at'  => isset($raw['inserted_at']) ? date('Y-m-d H:i:s', strtotime($raw['inserted_at'])) : null,
            'delivered_at' => null,
        ];
    }
}
