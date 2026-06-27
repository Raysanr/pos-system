<?php

namespace App\Jobs;

use App\Models\Customer;
use App\Models\PancakeShop;
use App\Models\SyncLog;
use App\Services\PancakeApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SyncCustomersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;
    public int $tries   = 1;

    // Process at most 200 pages per run so the job completes within the hour.
    // On the next hourly run the cursor resumes from where this run left off.
    private const MAX_PAGES_PER_RUN    = 200;
    // Abort early when the API returns this many consecutive page failures —
    // indicates an outage rather than a single stuck page.
    private const MAX_CONSECUTIVE_FAILS = 3;

    public function __construct(private readonly int $shopModelId) {}

    public function handle(): void
    {
        $shop = PancakeShop::findOrFail($this->shopModelId);

        $lockKey = "sync_customers_lock_{$shop->id}";
        $lock    = Cache::lock($lockKey, 3700);
        if (!$lock->get()) {
            $stale = SyncLog::where('shop_id', $shop->id)
                ->where('type', 'customers')
                ->where('status', 'running')
                ->where('started_at', '<', now()->subSeconds($this->timeout + 60))
                ->exists();

            if ($stale) {
                Log::warning("SyncCustomersJob: stale lock for shop {$shop->shop_id}, force-releasing.");
                Cache::lock($lockKey)->forceRelease();
                SyncLog::where('shop_id', $shop->id)
                    ->where('type', 'customers')
                    ->where('status', 'running')
                    ->whereNull('finished_at')
                    ->update(['status' => 'failed', 'error_message' => 'Stale lock — previous worker died.', 'finished_at' => now()]);
                $lock = Cache::lock($lockKey, 3700);
                if (!$lock->get()) {
                    Log::error("SyncCustomersJob: could not acquire lock after force-release for shop {$shop->shop_id}.");
                    return;
                }
            } else {
                Log::info("SyncCustomersJob: shop {$shop->shop_id} already syncing, skipping.");
                return;
            }
        }

        $log              = null;
        $resumeKey        = 'sync_customers_page_' . $shop->id;
        $startPage        = (int) Cache::get($resumeKey, 1);
        $totalFetched     = 0;
        $totalUpserted    = 0;
        $pagesProcessed   = 0;
        $consecutiveFails = 0;
        $skippedPages     = [];
        $cycleComplete    = false;

        try {
            $log = SyncLog::create([
                'shop_id'    => $shop->id,
                'type'       => 'customers',
                'status'     => 'running',
                'started_at' => now(),
            ]);

            $api  = PancakeApiService::forShop($shop);
            $page = $startPage;

            while ($pagesProcessed < self::MAX_PAGES_PER_RUN) {
                try {
                    $result = $api->getCustomers($page);
                    $batch  = $result['data'] ?? [];

                    if (empty($batch)) {
                        // No more pages — reached the end of the customer list.
                        Cache::forget($resumeKey);
                        $cycleComplete = true;
                        break;
                    }

                    $totalFetched  += count($batch);
                    $totalUpserted += $this->upsertBatch($shop->id, $batch);
                    Cache::put($resumeKey, $page + 1, now()->addDays(7));
                    $consecutiveFails = 0; // reset on success

                    if (count($batch) < PancakeApiService::PER_PAGE) {
                        Cache::forget($resumeKey);
                        $cycleComplete = true;
                        break;
                    }

                    $page++;
                    $pagesProcessed++;
                    usleep(200000); // 200 ms between pages
                } catch (\Throwable $e) {
                    $consecutiveFails++;
                    $skippedPages[] = $page;
                    Log::warning(
                        "SyncCustomersJob: page {$page} failed (attempt {$consecutiveFails}): {$e->getMessage()}"
                    );

                    if ($consecutiveFails >= self::MAX_CONSECUTIVE_FAILS) {
                        // API is likely down for this run — stop and resume from this page next time.
                        Log::warning(
                            "SyncCustomersJob: {$consecutiveFails} consecutive failures at page {$page}, aborting run."
                        );
                        break;
                    }

                    // Single stuck page — skip it and continue.
                    $page++;
                    $pagesProcessed++;
                    Cache::put($resumeKey, $page, now()->addDays(7));
                    usleep(1000000); // 1 s pause after a failure
                }
            }

            // Always update last_synced_at so the schedule doesn't re-dispatch immediately.
            // A partial sync is still useful — we'll continue from the cursor next hour.
            $shop->update(['last_synced_at' => now()]);

            $errorMsg = null;
            if (!empty($skippedPages)) {
                $errorMsg = 'Skipped pages: ' . implode(', ', $skippedPages);
            } elseif ($pagesProcessed >= self::MAX_PAGES_PER_RUN && !$cycleComplete) {
                $errorMsg = "Partial run: processed {$pagesProcessed} pages, resuming from page {$page} next run.";
            }

            $log->update([
                'status'           => 'success',
                'records_fetched'  => $totalFetched,
                'records_upserted' => $totalUpserted,
                'error_message'    => $errorMsg,
                'finished_at'      => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error("SyncCustomersJob failed for shop {$shop->shop_id}: {$e->getMessage()}");
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
            $r = $this->mapCustomer($shopId, $raw);
            foreach (['tags', 'pancake_tags'] as $field) {
                if (isset($r[$field]) && is_array($r[$field])) {
                    $r[$field] = json_encode($r[$field]);
                }
            }
            if (isset($r['utm_source']) && !is_string($r['utm_source'])) {
                $r['utm_source'] = json_encode($r['utm_source']);
            }
            $r['created_at'] = $now;
            $r['updated_at'] = $now;
            $records[]       = $r;
        }

        Customer::upsert($records, ['shop_id', 'pancake_id'], [
            'name', 'phone', 'email', 'gender', 'birthday',
            'province', 'district', 'ward', 'address',
            'customer_level', 'is_new_customer', 'is_wholesale',
            'total_orders', 'total_spent', 'reward_points', 'referred_by',
            'tags', 'pancake_tags', 'utm_source', 'last_order_at', 'updated_at',
        ]);

        return count($records);
    }

    private function mapCustomer(int $shopId, array $raw): array
    {
        $addr      = $raw['shop_customer_addresses'][0] ?? [];
        $addrParts = $this->parseAddressParts($addr['full_address'] ?? null);

        $province = $addr['province_name'] ?? $addr['city_name'] ?? $addrParts['province'];
        $district = $addr['district_name'] ?? $addrParts['district'];
        $ward     = $addr['commune_name']  ?? $addr['ward_name'] ?? $addrParts['ward'];

        return [
            'shop_id'         => $shopId,
            'pancake_id'      => (string) ($raw['id'] ?? ''),
            'name'            => $raw['name'] ?? null,
            'phone'           => $raw['phone_numbers'][0] ?? null,
            'email'           => $raw['emails'][0] ?? null,
            'gender'          => $raw['gender'] ?? null,
            'birthday'        => $this->parseDate($raw['date_of_birth'] ?? null),
            'province'        => $province,
            'district'        => $district,
            'ward'            => $ward,
            'address'         => $addr['full_address'] ?? null,
            'customer_level'  => $raw['level'] ?? null,
            'is_new_customer' => (int) ($raw['succeed_order_count'] ?? $raw['order_count'] ?? 0) === 0,
            'is_wholesale'    => false,
            'total_orders'    => (int) ($raw['order_count'] ?? 0),
            'total_spent'     => (float) ($raw['purchased_amount'] ?? 0),
            'reward_points'   => (float) ($raw['reward_point'] ?? 0),
            'referred_by'     => $raw['referral_code'] ?? null,
            'tags'            => $raw['conversation_tags'] ?? null,
            'pancake_tags'    => array_map(fn($t) => $t['name'] ?? '', $raw['tags'] ?? []) ?: null,
            'utm_source'      => isset($raw['order_sources'][0]) ? (string) $raw['order_sources'][0] : null,
            'first_order_at'  => null,
            'last_order_at'   => $this->parseDate($raw['last_order_at'] ?? null, true),
        ];
    }

    private function parseAddressParts(?string $fullAddress): array
    {
        if (!$fullAddress) return ['province' => null, 'district' => null, 'ward' => null];
        $parts = array_map('trim', explode(',', $fullAddress));
        $count = count($parts);
        return [
            'province' => $count >= 1 ? ($parts[$count - 1] ?: null) : null,
            'district' => $count >= 3 ? ($parts[$count - 2] ?: null) : null,
            'ward'     => $count >= 4 ? ($parts[$count - 3] ?: null) : null,
        ];
    }

    private function parseDate(?string $value, bool $withTime = false): ?string
    {
        if (!$value || $value === 'null') return null;
        $ts = strtotime($value);
        if (!$ts || $ts < 0) return null;
        return $withTime ? date('Y-m-d H:i:s', $ts) : date('Y-m-d', $ts);
    }
}
