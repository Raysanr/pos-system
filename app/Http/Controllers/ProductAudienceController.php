<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ProductAudienceController extends Controller
{
    public function index(Request $request)
    {
        $shop    = $this->user()->shops()->where('is_active', true)->firstOrFail();
        $products = $this->getProducts($shop->id);

        $selectedProduct = $request->input('product', $products[0] ?? null);
        $dateFrom        = $request->input('date_from') ?: null;
        $dateTo          = $request->input('date_to')   ?: null;
        $datePreset      = $this->detectDatePreset($dateFrom, $dateTo);

        $data = null;
        if ($selectedProduct) {
            $cacheKey = 'product_audience_' . $shop->id . '_' . $this->shopCacheBust($shop->id) . '_' . md5($selectedProduct) . "_{$dateFrom}_{$dateTo}";
            $data = Cache::remember($cacheKey, 1800, function () use ($shop, $selectedProduct, $dateFrom, $dateTo) {
                return [
                    'kpis'             => $this->kpis($shop->id, $selectedProduct, $dateFrom, $dateTo),
                    'ageGroups'        => $this->ageGroups($shop->id, $selectedProduct, $dateFrom, $dateTo),
                    'gender'           => $this->genderBreakdown($shop->id, $selectedProduct, $dateFrom, $dateTo),
                    'healthConditions' => $this->healthConditions($shop->id, $selectedProduct, $dateFrom, $dateTo),
                    'provinces'        => $this->topProvinces($shop->id, $selectedProduct, $dateFrom, $dateTo),
                    'newVsReturning'   => $this->newVsReturning($shop->id, $selectedProduct, $dateFrom, $dateTo),
                    'topCustomers'     => $this->topCustomers($shop->id, $selectedProduct, $dateFrom, $dateTo),
                ];
            });
        }

        if ($request->wantsJson()) {
            return response()->json([
                'data'            => $data,
                'selectedProduct' => $selectedProduct,
            ]);
        }

        return view('analytics.product-audience', compact(
            'shop', 'products', 'selectedProduct', 'dateFrom', 'dateTo', 'datePreset', 'data'
        ));
    }

    private function baseOrders(int $shopId, string $product, ?string $from, ?string $to)
    {
        $q = DB::table('orders')
            ->where('shop_id', $shopId)
            ->whereRaw(
                "EXISTS (SELECT 1 FROM json_each(items) as item WHERE json_extract(item.value, '$.variation_info.name') = ? COLLATE NOCASE)",
                [$product]
            );
        if ($from) $q->where('ordered_at', '>=', $from . ' 00:00:00');
        if ($to)   $q->where('ordered_at', '<=', $to   . ' 23:59:59');
        return $q;
    }

    private function kpis(int $shopId, string $product, ?string $from, ?string $to): array
    {
        $row = (clone $this->baseOrders($shopId, $product, $from, $to))
            ->selectRaw("
                COUNT(*) as total_orders,
                AVG(CASE WHEN customer_age > 0 THEN CAST(customer_age AS REAL) END) as avg_age,
                SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered,
                SUM(CASE WHEN is_rts = 1 THEN 1 ELSE 0 END) as rts,
                SUM(CASE WHEN status = 'delivered' THEN total_price ELSE 0 END) as revenue
            ")
            ->first();

        $total = (int) $row->total_orders;
        return [
            'total_orders'  => $total,
            'avg_age'       => $row->avg_age ? (int) round($row->avg_age) : null,
            'deliver_rate'  => $total > 0 ? round($row->delivered / $total * 100, 1) : 0,
            'rts_rate'      => $total > 0 ? round($row->rts / $total * 100, 1) : 0,
            'revenue'       => number_format((float) $row->revenue),
            'total_bottles' => $this->sumBottles($shopId, $from, $to, $product, ["o.status = 'delivered'"]),
        ];
    }

    private function ageGroups(int $shopId, string $product, ?string $from, ?string $to): array
    {
        $rows = (clone $this->baseOrders($shopId, $product, $from, $to))
            ->whereNotNull('customer_age')
            ->selectRaw("
                CASE
                    WHEN customer_age < 40 THEN 'Under 40'
                    WHEN customer_age < 50 THEN '40–49'
                    WHEN customer_age < 60 THEN '50–59'
                    WHEN customer_age < 70 THEN '60–69'
                    WHEN customer_age < 80 THEN '70–79'
                    ELSE '80+'
                END as age_group,
                COUNT(*) as cnt
            ")
            ->groupBy('age_group')
            ->get()
            ->keyBy('age_group');

        $order = ['Under 40', '40–49', '50–59', '60–69', '70–79', '80+'];
        return [
            'labels' => $order,
            'data'   => array_map(fn($l) => (int) ($rows[$l]->cnt ?? 0), $order),
        ];
    }

    private function genderBreakdown(int $shopId, string $product, ?string $from, ?string $to): array
    {
        $rows = DB::table('customers as c')
            ->where('c.shop_id', $shopId)
            ->whereNotNull('c.gender')
            ->whereIn('c.pancake_id', function ($sub) use ($shopId, $product, $from, $to) {
                $sub->from('orders')
                    ->select('customer_pancake_id')
                    ->where('shop_id', $shopId)
                    ->whereNotNull('customer_pancake_id')
                    ->whereRaw(
                        "EXISTS (SELECT 1 FROM json_each(items) as item WHERE json_extract(item.value, '$.variation_info.name') = ? COLLATE NOCASE)",
                        [$product]
                    );
                if ($from) $sub->where('ordered_at', '>=', $from . ' 00:00:00');
                if ($to)   $sub->where('ordered_at', '<=', $to   . ' 23:59:59');
            })
            ->select('c.gender', DB::raw('COUNT(*) as cnt'))
            ->groupBy('c.gender')
            ->get();

        $map = [];
        foreach ($rows as $r) {
            $key = match (strtolower(trim((string) $r->gender))) {
                'male', 'lalaki'  => 'Male',
                'female', 'babae' => 'Female',
                default           => 'Unknown',
            };
            $map[$key] = ($map[$key] ?? 0) + (int) $r->cnt;
        }
        arsort($map);

        return ['labels' => array_keys($map), 'data' => array_values($map)];
    }

    private function healthConditions(int $shopId, string $product, ?string $from, ?string $to): array
    {
        // health_condition is stored as a JSON array e.g. ["Cataract","Teary Eyes"].
        // json_each() expands each element so one order can contribute multiple conditions.
        // LIKE '[%' guards against any legacy plain-string rows from before the JSON migration.
        $bindings = [$shopId, $product];
        $dateWhere = '';
        if ($from) { $dateWhere .= " AND o.ordered_at >= ?"; $bindings[] = $from . ' 00:00:00'; }
        if ($to)   { $dateWhere .= " AND o.ordered_at <= ?"; $bindings[] = $to   . ' 23:59:59'; }

        $rows = DB::select("
            SELECT je.value AS condition, COUNT(*) AS cnt
            FROM orders o, json_each(o.health_condition) AS je
            WHERE o.shop_id = ?
              AND o.health_condition IS NOT NULL
              AND o.health_condition LIKE '[%'
              AND EXISTS (
                  SELECT 1 FROM json_each(o.items) AS item
                  WHERE json_extract(item.value, '\$.variation_info.name') = ? COLLATE NOCASE
              )
              {$dateWhere}
            GROUP BY je.value
            ORDER BY cnt DESC
            LIMIT 12
        ", $bindings);

        return [
            'labels' => array_column($rows, 'condition'),
            'data'   => array_map('intval', array_column($rows, 'cnt')),
        ];
    }

    private function topProvinces(int $shopId, string $product, ?string $from, ?string $to): array
    {
        $rows = (clone $this->baseOrders($shopId, $product, $from, $to))
            ->whereNotNull('province')
            ->select('province', DB::raw('COUNT(*) as cnt'))
            ->groupBy('province')
            ->orderByDesc('cnt')
            ->take(15)
            ->get();

        return [
            'labels' => $rows->pluck('province')->toArray(),
            'data'   => $rows->pluck('cnt')->map(fn($v) => (int) $v)->toArray(),
        ];
    }

    private function newVsReturning(int $shopId, string $product, ?string $from, ?string $to): array
    {
        $sub = DB::table('orders')
            ->where('shop_id', $shopId)
            ->whereNotNull('customer_pancake_id')
            ->whereRaw(
                "EXISTS (SELECT 1 FROM json_each(items) as item WHERE json_extract(item.value, '$.variation_info.name') = ? COLLATE NOCASE)",
                [$product]
            );

        if ($from) $sub->where('ordered_at', '>=', $from . ' 00:00:00');
        if ($to)   $sub->where('ordered_at', '<=', $to   . ' 23:59:59');

        $sub->select('customer_pancake_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('customer_pancake_id');

        $row = DB::table($sub, 'sub')->selectRaw(
            'SUM(CASE WHEN cnt = 1 THEN 1 ELSE 0 END) as new_count, ' .
            'SUM(CASE WHEN cnt > 1 THEN 1 ELSE 0 END) as ret_count'
        )->first();

        return [
            'new'       => (int) ($row->new_count ?? 0),
            'returning' => (int) ($row->ret_count ?? 0),
        ];
    }

    private function topCustomers(int $shopId, string $product, ?string $from, ?string $to): array
    {
        $q = DB::table('orders as o')
            ->join('customers as c', function ($j) use ($shopId) {
                $j->on('c.pancake_id', '=', 'o.customer_pancake_id')
                  ->where('c.shop_id', $shopId);
            })
            ->where('o.shop_id', $shopId)
            ->whereNotNull('o.customer_pancake_id')
            ->whereRaw(
                "EXISTS (SELECT 1 FROM json_each(o.items) as item WHERE json_extract(item.value, '$.variation_info.name') = ? COLLATE NOCASE)",
                [$product]
            );

        if ($from) $q->where('o.ordered_at', '>=', $from . ' 00:00:00');
        if ($to)   $q->where('o.ordered_at', '<=', $to   . ' 23:59:59');

        return $q->select(
                'c.name', 'c.phone', 'c.province', 'c.gender',
                DB::raw('COUNT(o.id) as order_count'),
                DB::raw('SUM(CASE WHEN o.status = \'delivered\' THEN o.total_price ELSE 0 END) as spent')
            )
            ->groupBy('o.customer_pancake_id', 'c.name', 'c.phone', 'c.province', 'c.gender')
            ->orderByDesc('spent')
            ->take(15)
            ->get()
            ->toArray();
    }
}
