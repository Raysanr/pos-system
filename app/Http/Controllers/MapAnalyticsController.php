<?php
namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\GeocodingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MapAnalyticsController extends Controller
{
    public function index(Request $request)
    {
        $shop     = $this->user()->shops()->where('is_active', true)->firstOrFail();
        $dateFrom = $request->input('date_from', now()->subDays(30)->format('Y-m-d')) ?: null;
        $dateTo   = $request->input('date_to',   now()->format('Y-m-d'))              ?: null;
        $status   = $request->input('status', 'all');
        $level    = in_array($request->input('level'), ['province', 'city', 'barangay'])
                    ? $request->input('level') : 'province';
        $provinceFilter = $request->input('province_filter');
        $cityFilter     = $request->input('city_filter');
        $productFilter  = $request->input('product_filter');
        $products       = $this->getProducts($shop->id);
        $datePreset     = $this->detectDatePreset($dateFrom, $dateTo);

        $cacheKey = "map_{$shop->id}_{$dateFrom}_{$dateTo}_{$status}_{$level}_{$provinceFilter}_{$cityFilter}_{$productFilter}";
        try {
            $mapData = Cache::remember($cacheKey, 1800, function () use ($shop, $dateFrom, $dateTo, $status, $level, $provinceFilter, $cityFilter, $productFilter) {
                $data   = $this->getLocationData($shop->id, $dateFrom, $dateTo, $status, $level, $provinceFilter, $cityFilter, $productFilter);
                $result = app(GeocodingService::class)->attachCoords($data, $level, $provinceFilter);
                $hasPoints = count($data) > 0;
                $hasCoords = !empty(array_filter($result, fn($r) => !empty($r['lat']) || !empty($r['lng'])));
                if ($hasPoints && !$hasCoords) {
                    throw new \RuntimeException('Geocoding returned no coordinates — skipping cache.');
                }
                return $result;
            });
        } catch (\RuntimeException $e) {
            Log::warning("MapAnalyticsController: {$e->getMessage()}");
            $mapData = $this->getLocationData($shop->id, $dateFrom, $dateTo, $status, $level, $provinceFilter, $cityFilter, $productFilter);
        }

        if ($request->expectsJson()) {
            return response()->json($mapData);
        }

        return view('analytics.map', compact('shop', 'mapData', 'dateFrom', 'dateTo', 'status', 'level', 'products', 'productFilter', 'datePreset'));
    }

    private function getLocationData(
        int $shopId, ?string $from, ?string $to, string $status,
        string $level = 'province', ?string $provinceFilter = null, ?string $cityFilter = null,
        ?string $productFilter = null
    ): array {
        $groupCol = match ($level) {
            'city'     => 'district',
            'barangay' => 'ward',
            default    => 'province',
        };

        $query = Order::where('shop_id', $shopId)->whereNotNull($groupCol);
        $this->applyDateFilter($query, $from, $to);

        if ($provinceFilter) $query->where('province', $provinceFilter);
        if ($cityFilter)     $query->where('district', $cityFilter);
        $this->applyProductFilter($query, $productFilter);

        if ($status !== 'all') {
            match ($status) {
                'delivered' => $query->where('status', 'delivered'),
                'rts'       => $query->where('is_rts', true),
                'returned'  => $query->where('is_returned', true),
                'cancelled' => $query->where('is_cancelled', true),
                default     => null,
            };
        }

        $selectCols = [
            DB::raw("{$groupCol} as location_name"),
            DB::raw('COUNT(*) as total_orders'),
            DB::raw("SUM(CASE WHEN status='delivered' THEN total_price ELSE 0 END) as total_revenue"),
            DB::raw('SUM(CASE WHEN is_rts=1 THEN 1 ELSE 0 END) as rts_count'),
            DB::raw('ROUND(SUM(CASE WHEN is_rts=1 THEN 1 ELSE 0 END)*100.0/COUNT(*),1) as rts_rate'),
            DB::raw("AVG(CASE WHEN status='delivered' THEN total_price ELSE NULL END) as avg_order_value"),
        ];

        if ($level === 'city')     $selectCols[] = DB::raw('MAX(province) as parent_province');
        if ($level === 'barangay') {
            $selectCols[] = DB::raw('MAX(province) as parent_province');
            $selectCols[] = DB::raw('MAX(district) as parent_city');
        }

        return $query->select($selectCols)
        ->groupBy($groupCol)
        ->orderByDesc('total_orders')
        ->get()
        ->map(fn($r) => [
            'name'            => $r->location_name,
            'province'        => $r->parent_province ?? null,
            'city'            => $r->parent_city     ?? null,
            'total_orders'    => (int)   $r->total_orders,
            'total_revenue'   => round($r->total_revenue),
            'rts_count'       => (int)   $r->rts_count,
            'rts_rate'        => (float) $r->rts_rate,
            'avg_order_value' => round($r->avg_order_value ?? 0),
        ])
        ->toArray();
    }
}
