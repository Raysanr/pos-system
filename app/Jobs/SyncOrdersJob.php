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
use Illuminate\Support\Facades\Log;

class SyncOrdersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;
    public int $tries = 2;

    public function __construct(
        private readonly int $shopModelId,
        private readonly ?string $fromDate = null,
    ) {}

    public function handle(): void
    {
        $shop = PancakeShop::findOrFail($this->shopModelId);
        $log = SyncLog::create([
            'shop_id' => $shop->id,
            'type' => 'orders',
            'status' => 'running',
            'started_at' => now(),
        ]);

        try {
            $api = PancakeApiService::forShop($shop);
            $filters = $this->fromDate ? ['from_date' => $this->fromDate] : [];
            $totalFetched = 0;
            $totalUpserted = 0;

            foreach ($api->getAllOrders(null, $filters) as $batch) {
                $totalFetched += count($batch);
                $totalUpserted += $this->upsertBatch($shop->id, $batch);
            }

            $log->update([
                'status' => 'success',
                'records_fetched' => $totalFetched,
                'records_upserted' => $totalUpserted,
                'finished_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error("Orders sync failed for shop {$shop->shop_id}: {$e->getMessage()}");
            $log->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'finished_at' => now(),
            ]);
        }
    }

    private function upsertBatch(int $shopId, array $batch): int
    {
        $now = now()->format('Y-m-d H:i:s');
        $records = [];

        foreach ($batch as $raw) {
            $r = $this->mapOrder($shopId, $raw);
            $r['items']      = isset($r['items'])    ? json_encode($r['items'])    : null;
            $r['utm_data']   = isset($r['utm_data']) ? json_encode($r['utm_data']) : null;
            $r['created_at'] = $now;
            $r['updated_at'] = $now;
            $records[] = $r;
        }

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

        return count($records);
    }

    private function mapOrder(int $shopId, array $raw): array
    {
        // Pancake POS status codes: 0=new, 2=shipped, 3=delivered, 4=returning, 5=returned, 6=canceled, 11=waiting
        $status = (int)($raw['status'] ?? -1);
        $addr   = $raw['shipping_address'] ?? [];
        $partner = $raw['partner'] ?? [];
        $cod    = (float)($raw['cod'] ?? 0);
        $prepaid = (float)($raw['prepaid'] ?? 0);

        return [
            'shop_id'             => $shopId,
            'pancake_id'          => (string)($raw['id'] ?? ''),
            'order_code'          => (string)($raw['id'] ?? ''),
            'customer_pancake_id' => (string)($raw['customer']['id'] ?? ''),
            'customer_name'       => $raw['bill_full_name'] ?? null,
            'customer_phone'      => $raw['bill_phone_number'] ?? null,
            'status'              => $raw['status_name'] ?? (string)$status,
            'payment_method'      => $cod > 0 ? 'COD' : ($prepaid > 0 ? 'Prepaid' : 'Unknown'),
            'payment_status'      => null,
            'total_price'         => (float)($raw['total_price'] ?? 0),
            'shipping_fee'        => (float)($raw['shipping_fee'] ?? 0),
            'discount'            => (float)($raw['total_discount'] ?? 0),
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
            'is_wholesale'        => (bool)($raw['is_exchange_order'] ?? false),
            'extra_note'          => $raw['note'] ?? null,
            'return_reason'       => $raw['returned_reason_name'] ?? null,
            'customer_age'        => DemographicsExtractor::age($raw['note'] ?? null),
            'health_condition'    => DemographicsExtractor::condition($raw['note'] ?? null),
            'items'               => $raw['items'] ?? null,
            'utm_data'            => array_filter([
                'source'   => $raw['p_utm_source'] ?? null,
                'medium'   => $raw['p_utm_medium'] ?? null,
                'campaign' => $raw['p_utm_campaign'] ?? null,
            ]) ?: null,
            'ordered_at'          => isset($raw['inserted_at']) ? date('Y-m-d H:i:s', strtotime($raw['inserted_at'])) : null,
            'delivered_at'        => null,
        ];
    }
}
