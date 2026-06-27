<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SalesForecastController extends Controller
{
    public function index(Request $request)
    {
        $shop     = $this->user()->shops()->where('is_active', true)->firstOrFail();
        $products = $this->getProducts($shop->id);

        $cacheKey = "forecast_{$shop->id}_{$this->shopCacheBust($shop->id)}";
        $history  = Cache::remember($cacheKey, 1800, fn() => $this->getDailyHistory($shop->id));

        // Build per-product forecast
        $forecasts = $this->buildForecasts($history);

        // Summary KPIs across all products
        $totalProjectedOrders  = array_sum(array_column($forecasts, 'projected_orders'));
        $totalProjectedRevenue = array_sum(array_column($forecasts, 'projected_revenue'));
        $topProduct            = $forecasts ? $forecasts[0] : null;

        // Build the 90-day date spine and 30-day future spine
        $today      = now()->format('Y-m-d');
        $histStart  = now()->subDays(89)->format('Y-m-d');
        $histDates  = $this->dateSeries($histStart, $today);
        $futureStart = now()->addDay()->format('Y-m-d');
        $futureEnd   = now()->addDays(30)->format('Y-m-d');
        $futureDates = $this->dateSeries($futureStart, $futureEnd);

        return view('analytics.forecast', compact(
            'shop', 'products', 'forecasts',
            'totalProjectedOrders', 'totalProjectedRevenue', 'topProduct',
            'histDates', 'futureDates', 'history'
        ));
    }

    private function getDailyHistory(int $shopId): array
    {
        $sql = "
            SELECT
                LOWER(json_extract(item.value, '$.variation_info.name')) AS product,
                DATE(o.ordered_at) AS order_date,
                COUNT(DISTINCT o.id) AS orders,
                SUM(CASE WHEN o.status = 'delivered' THEN o.total_price ELSE 0 END) AS revenue
            FROM orders o
            JOIN json_each(o.items) AS item
            WHERE o.shop_id = ?
              AND o.ordered_at >= DATE('now', '-89 days')
              AND o.ordered_at <= DATE('now', '+1 day')
              AND json_extract(item.value, '$.variation_info.name') IS NOT NULL
            GROUP BY product, order_date
            ORDER BY product, order_date
        ";

        $rows = DB::select($sql, [$shopId]);
        return array_map(fn($r) => [
            'product'    => $r->product,
            'order_date' => $r->order_date,
            'orders'     => (int)   $r->orders,
            'revenue'    => (float) $r->revenue,
        ], $rows);
    }

    private function buildForecasts(array $history): array
    {
        // Group by product
        $byProduct = [];
        foreach ($history as $row) {
            $byProduct[$row['product']][] = $row;
        }

        $today     = now()->format('Y-m-d');
        $histStart = now()->subDays(89)->format('Y-m-d');
        $histDates = $this->dateSeries($histStart, $today);

        $result = [];
        foreach ($byProduct as $product => $rows) {
            // Fill date gaps with zeros
            $indexed = [];
            foreach ($rows as $r) {
                $indexed[$r['order_date']] = $r;
            }

            $ordersArr  = [];
            $revenueArr = [];
            foreach ($histDates as $d) {
                $ordersArr[]  = $indexed[$d]['orders']  ?? 0;
                $revenueArr[] = $indexed[$d]['revenue'] ?? 0;
            }

            // Weighted moving average: last 14 days get 2x weight vs earlier days
            $n = count($ordersArr);
            if ($n === 0) continue;

            // Use last 30 days for the forecast base (more representative of current trend)
            $recent = array_slice($ordersArr, -30);
            $recentRev = array_slice($revenueArr, -30);

            // Linear regression on last 30 days
            [$slopeO, $interceptO] = $this->linReg($recent);
            [$slopeR, $interceptR] = $this->linReg($recentRev);

            // Project 30 days: day 31 to day 60 from the regression start
            $projOrders  = 0;
            $projRevenue = 0;
            $dailyOrders  = [];
            $dailyRevenue = [];
            for ($i = 1; $i <= 30; $i++) {
                $x = 30 + $i; // continuing from the 30-day window
                $o = max(0, round($interceptO + $slopeO * $x));
                $r = max(0, round($interceptR + $slopeR * $x));
                $dailyOrders[]  = (int) $o;
                $dailyRevenue[] = (float) $r;
                $projOrders  += $o;
                $projRevenue += $r;
            }

            // Actual 90-day totals for comparison
            $actual90Orders  = array_sum($ordersArr);
            $actual90Revenue = array_sum($revenueArr);
            $daily90Avg      = $n > 0 ? $actual90Orders / $n : 0;

            // Trend direction (slope of last 30 days)
            $trend = $slopeO > 0.1 ? 'up' : ($slopeO < -0.1 ? 'down' : 'flat');

            $result[] = [
                'product'           => $product,
                'projected_orders'  => (int) $projOrders,
                'projected_revenue' => (float) $projRevenue,
                'actual_90_orders'  => (int) $actual90Orders,
                'actual_90_revenue' => (float) $actual90Revenue,
                'daily_avg'         => round($daily90Avg, 1),
                'trend'             => $trend,
                'slope'             => round($slopeO, 3),
                'history_orders'    => $ordersArr,
                'history_revenue'   => $revenueArr,
                'future_orders'     => $dailyOrders,
                'future_revenue'    => $dailyRevenue,
            ];
        }

        // Sort by projected revenue desc
        usort($result, fn($a, $b) => $b['projected_revenue'] <=> $a['projected_revenue']);

        return $result;
    }

    // Ordinary least squares linear regression: returns [slope, intercept]
    private function linReg(array $y): array
    {
        $n = count($y);
        if ($n < 2) return [0, $n === 1 ? $y[0] : 0];

        $sumX = 0; $sumY = 0; $sumXY = 0; $sumXX = 0;
        for ($i = 0; $i < $n; $i++) {
            $sumX  += $i;
            $sumY  += $y[$i];
            $sumXY += $i * $y[$i];
            $sumXX += $i * $i;
        }

        $denom = $n * $sumXX - $sumX * $sumX;
        if ($denom == 0) return [0, $sumY / $n];

        $slope     = ($n * $sumXY - $sumX * $sumY) / $denom;
        $intercept = ($sumY - $slope * $sumX) / $n;

        return [$slope, $intercept];
    }

    private function dateSeries(string $start, string $end): array
    {
        $dates  = [];
        $cursor = new \DateTime($start);
        $endDt  = new \DateTime($end);
        while ($cursor <= $endDt) {
            $dates[] = $cursor->format('Y-m-d');
            $cursor->modify('+1 day');
        }
        return $dates;
    }
}
