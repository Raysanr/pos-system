<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SeasonalTrendController extends Controller
{
    private array $phHolidays = [
        '2026-01-01' => "New Year's Day",
        '2026-02-25' => 'EDSA Revolution',
        '2026-04-02' => 'Maundy Thursday',
        '2026-04-03' => 'Good Friday',
        '2026-04-04' => 'Black Saturday',
        '2026-04-09' => 'Araw ng Kagitingan',
        '2026-05-01' => 'Labor Day',
        '2026-06-12' => 'Independence Day',
        '2026-08-21' => 'Ninoy Aquino Day',
        '2026-08-31' => 'National Heroes Day',
        '2026-11-01' => 'All Saints Day',
        '2026-11-02' => 'All Souls Day',
        '2026-11-30' => 'Bonifacio Day',
        '2026-12-25' => 'Christmas Day',
        '2026-12-30' => 'Rizal Day',
        '2026-12-31' => "New Year's Eve",
    ];

    public function index(Request $request)
    {
        $shop          = $this->user()->shops()->where('is_active', true)->firstOrFail();
        $productFilter = $request->input('product_filter') ?: null;
        $products      = $this->getProducts($shop->id);

        $cacheKey = 'seasonal_' . $shop->id . '_' . $this->shopCacheBust($shop->id) . '_' . md5((string) $productFilter);
        $data = Cache::remember($cacheKey, 1800, function () use ($shop, $productFilter) {
            return [
                'daily'      => $this->dailyHeatmap($shop->id, $productFilter),
                'weekly'     => $this->weeklyTrend($shop->id, $productFilter),
                'dayOfMonth' => $this->dayOfMonthPattern($shop->id, $productFilter),
                'dayOfWeek'  => $this->dayOfWeekPattern($shop->id, $productFilter),
                'monthly'    => $this->monthlySummary($shop->id, $productFilter),
            ];
        });

        return view('analytics.seasonal', compact('shop', 'products', 'productFilter', 'data'));
    }

    // Returns extra AND clause + appends product param to $params array
    private function withProduct(?string $pf, array &$params): string
    {
        if (!$pf) return '';
        $params[] = $pf;
        return "AND EXISTS (SELECT 1 FROM json_each(o.items) AS item WHERE json_extract(item.value, '$.variation_info.name') = ? COLLATE NOCASE)";
    }

    private function dailyHeatmap(int $shopId, ?string $pf): array
    {
        $params = [$shopId];
        $productClause = $this->withProduct($pf, $params);

        $rows = DB::select("
            SELECT date(o.ordered_at) as day,
                   COUNT(*) as orders,
                   SUM(CASE WHEN o.status='delivered' THEN 1 ELSE 0 END) as delivered,
                   SUM(CASE WHEN o.status='delivered' THEN o.total_price ELSE 0 END) as revenue
            FROM orders o
            WHERE o.shop_id = ? AND o.ordered_at IS NOT NULL
              AND o.ordered_at >= date('now', '-364 days')
              AND o.ordered_at <= date('now', '+1 day')
            {$productClause}
            GROUP BY day ORDER BY day
        ", $params);

        $result = [];
        foreach ($rows as $r) {
            $result[$r->day] = [
                'orders'    => (int) $r->orders,
                'delivered' => (int) $r->delivered,
                'revenue'   => (float) $r->revenue,
            ];
        }
        return $result;
    }

    private function weeklyTrend(int $shopId, ?string $pf): array
    {
        $params = [$shopId];
        $productClause = $this->withProduct($pf, $params);

        $rows = DB::select("
            SELECT strftime('%Y-%W', o.ordered_at) as week,
                   MIN(date(o.ordered_at)) as week_start,
                   COUNT(*) as orders,
                   SUM(CASE WHEN o.status='delivered' THEN 1 ELSE 0 END) as delivered,
                   SUM(CASE WHEN o.is_rts=1 THEN 1 ELSE 0 END) as rts,
                   SUM(CASE WHEN o.status='delivered' THEN o.total_price ELSE 0 END) as revenue
            FROM orders o
            WHERE o.shop_id = ? AND o.ordered_at IS NOT NULL AND o.ordered_at <= date('now')
            {$productClause}
            GROUP BY week ORDER BY week
        ", $params);

        return array_map(fn($r) => [
            'week'       => $r->week,
            'week_start' => $r->week_start,
            'orders'     => (int) $r->orders,
            'delivered'  => (int) $r->delivered,
            'rts'        => (int) $r->rts,
            'revenue'    => (float) $r->revenue,
        ], $rows);
    }

    private function dayOfMonthPattern(int $shopId, ?string $pf): array
    {
        $params = [$shopId];
        $productClause = $this->withProduct($pf, $params);

        $rows = DB::select("
            SELECT CAST(strftime('%d', o.ordered_at) AS INTEGER) as dom,
                   COUNT(*) as orders,
                   SUM(CASE WHEN o.status='delivered' THEN 1 ELSE 0 END) as delivered,
                   SUM(CASE WHEN o.status='delivered' THEN o.total_price ELSE 0 END) as revenue
            FROM orders o
            WHERE o.shop_id = ? AND o.ordered_at IS NOT NULL AND o.ordered_at <= date('now')
            {$productClause}
            GROUP BY dom ORDER BY dom
        ", $params);

        $result = array_fill(1, 31, ['orders' => 0, 'delivered' => 0, 'revenue' => 0.0]);
        foreach ($rows as $r) {
            $result[(int) $r->dom] = [
                'orders'    => (int) $r->orders,
                'delivered' => (int) $r->delivered,
                'revenue'   => (float) $r->revenue,
            ];
        }
        return $result;
    }

    private function dayOfWeekPattern(int $shopId, ?string $pf): array
    {
        $params = [$shopId];
        $productClause = $this->withProduct($pf, $params);

        $rows = DB::select("
            SELECT CAST(strftime('%w', o.ordered_at) AS INTEGER) as dow,
                   COUNT(*) as orders,
                   SUM(CASE WHEN o.status='delivered' THEN 1 ELSE 0 END) as delivered,
                   SUM(CASE WHEN o.status='delivered' THEN o.total_price ELSE 0 END) as revenue
            FROM orders o
            WHERE o.shop_id = ? AND o.ordered_at IS NOT NULL AND o.ordered_at <= date('now')
            {$productClause}
            GROUP BY dow ORDER BY dow
        ", $params);

        $result = array_fill(0, 7, ['orders' => 0, 'delivered' => 0, 'revenue' => 0.0]);
        foreach ($rows as $r) {
            $result[(int) $r->dow] = [
                'orders'    => (int) $r->orders,
                'delivered' => (int) $r->delivered,
                'revenue'   => (float) $r->revenue,
            ];
        }
        return $result;
    }

    private function monthlySummary(int $shopId, ?string $pf): array
    {
        $params = [$shopId];
        $productClause = $this->withProduct($pf, $params);

        $rows = DB::select("
            SELECT strftime('%Y-%m', o.ordered_at) as month,
                   COUNT(*) as orders,
                   SUM(CASE WHEN o.status='delivered' THEN 1 ELSE 0 END) as delivered,
                   SUM(CASE WHEN o.is_rts=1 THEN 1 ELSE 0 END) as rts,
                   SUM(CASE WHEN o.status='delivered' THEN o.total_price ELSE 0 END) as revenue
            FROM orders o
            WHERE o.shop_id = ? AND o.ordered_at IS NOT NULL AND o.ordered_at <= date('now')
            {$productClause}
            GROUP BY month ORDER BY month
        ", $params);

        return array_map(fn($r) => [
            'month'     => $r->month,
            'orders'    => (int) $r->orders,
            'delivered' => (int) $r->delivered,
            'rts'       => (int) $r->rts,
            'revenue'   => (float) $r->revenue,
        ], $rows);
    }
}
