<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CustomerLtvController extends Controller
{
    public function index(Request $request)
    {
        $shop = $this->user()->shops()->where('is_active', true)->firstOrFail();

        $cacheKey = "product_comparison_{$shop->id}_{$this->shopCacheBust($shop->id)}";
        $raw = Cache::remember($cacheKey, 1800, function () use ($shop) {
            return [
                'productLtv'   => $this->getProductLtv($shop->id),
                'cohortLtv'    => $this->getCohortLtv($shop->id),
                'monthlyTrend' => $this->getMonthlyTrend($shop->id),
                'rtsData'      => $this->getNewCustomerRts($shop->id),
            ];
        });

        // Last 12 months oldest → newest
        $months = [];
        for ($i = 11; $i >= 0; $i--) {
            $months[] = now()->subMonths($i)->format('Y-m');
        }
        $lastMonth      = now()->subMonth()->format('Y-m');
        $prevMonth      = now()->subMonths(2)->format('Y-m');
        $lastMonthLabel = now()->subMonth()->format('M Y');

        // Index acquisition data by product
        $monthlyByProduct = [];
        foreach ($raw['monthlyTrend'] as $row) {
            $monthlyByProduct[$row['product']][$row['month']] = $row['new_customers'];
        }
        $rtsByProduct = [];
        foreach ($raw['rtsData'] as $row) {
            $rtsByProduct[$row['product']] = $row;
        }

        // Merge LTV + acquisition into unified product cards
        $productData = array_map(function ($p) use ($monthlyByProduct, $rtsByProduct, $months, $lastMonth, $prevMonth) {
            $monthly    = $monthlyByProduct[$p['product']] ?? [];
            $rts        = $rtsByProduct[$p['product']] ?? null;
            $lastCount  = $monthly[$lastMonth] ?? 0;
            $prevCount  = $monthly[$prevMonth] ?? 0;
            $growthRate = $prevCount > 0
                ? round(($lastCount - $prevCount) / $prevCount * 100, 1)
                : null;

            return array_merge($p, [
                'last_month'  => $lastCount,
                'growth_rate' => $growthRate,
                'rts_rate'    => $rts ? (float) $rts['rts_rate'] : null,
                'total_12mo'  => array_sum($monthly),
                'monthly'     => array_map(fn($m) => $monthly[$m] ?? 0, $months),
            ]);
        }, $raw['productLtv']);

        $cohortLtv      = $raw['cohortLtv'];
        $maxLtv         = $productData ? $productData[0]['avg_ltv'] : 1;
        $totalCustomers = array_sum(array_column($productData, 'customer_count'));
        $overallAvgLtv  = $totalCustomers > 0
            ? array_sum(array_column($productData, 'total_revenue')) / $totalCustomers
            : 0;
        $bestRepeat     = $productData
            ? collect($productData)->sortByDesc('repeat_rate')->first()
            : null;
        $totalLastMonth = array_sum(array_column($productData, 'last_month'));
        $lowestRts      = $productData
            ? collect($productData)->whereNotNull('rts_rate')->sortBy('rts_rate')->first()
            : null;

        return view('analytics.ltv', compact(
            'shop', 'months', 'lastMonthLabel', 'productData', 'cohortLtv',
            'maxLtv', 'overallAvgLtv', 'totalCustomers',
            'bestRepeat', 'totalLastMonth', 'lowestRts'
        ));
    }

    private function getProductLtv(int $shopId): array
    {
        $sql = "
            WITH customer_first AS (
                SELECT customer_pancake_id, MIN(ordered_at) AS first_at
                FROM orders
                WHERE shop_id = ? AND customer_pancake_id IS NOT NULL AND ordered_at IS NOT NULL
                GROUP BY customer_pancake_id
            ),
            customer_entry AS (
                SELECT cf.customer_pancake_id,
                       cf.first_at,
                       LOWER(json_extract(item.value, '$.variation_info.name')) AS entry_product
                FROM customer_first cf
                JOIN orders o ON o.customer_pancake_id = cf.customer_pancake_id
                             AND o.ordered_at = cf.first_at
                             AND o.shop_id = ?
                JOIN json_each(o.items) AS item
                WHERE json_extract(item.value, '$.variation_info.name') IS NOT NULL
                GROUP BY cf.customer_pancake_id
            ),
            customer_totals AS (
                SELECT customer_pancake_id,
                       COUNT(*) AS total_orders,
                       SUM(CASE WHEN status = 'delivered' THEN total_price ELSE 0 END) AS total_revenue,
                       SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) AS delivered_orders
                FROM orders
                WHERE shop_id = ?
                GROUP BY customer_pancake_id
            )
            SELECT
                ce.entry_product                                                        AS product,
                COUNT(DISTINCT ce.customer_pancake_id)                                 AS customer_count,
                ROUND(AVG(ct.total_revenue), 0)                                        AS avg_ltv,
                ROUND(AVG(ct.total_orders), 1)                                         AS avg_orders,
                ROUND(AVG(ct.delivered_orders), 1)                                     AS avg_delivered,
                ROUND(SUM(ct.total_revenue), 0)                                        AS total_revenue,
                ROUND(AVG(CASE WHEN ct.delivered_orders > 0
                               THEN ct.total_revenue / ct.delivered_orders
                               ELSE 0 END), 0)                                         AS avg_order_value,
                ROUND(SUM(CASE WHEN ct.total_orders > 1 THEN 1 ELSE 0 END) * 100.0
                      / COUNT(DISTINCT ce.customer_pancake_id), 1)                     AS repeat_rate
            FROM customer_entry ce
            JOIN customer_totals ct ON ct.customer_pancake_id = ce.customer_pancake_id
            GROUP BY ce.entry_product
            HAVING COUNT(DISTINCT ce.customer_pancake_id) >= 5
            ORDER BY avg_ltv DESC
        ";

        $rows = DB::select($sql, [$shopId, $shopId, $shopId]);
        return array_map(fn($r) => [
            'product'         => $r->product,
            'customer_count'  => (int)   $r->customer_count,
            'avg_ltv'         => (float) $r->avg_ltv,
            'avg_orders'      => (float) $r->avg_orders,
            'avg_delivered'   => (float) $r->avg_delivered,
            'total_revenue'   => (float) $r->total_revenue,
            'avg_order_value' => (float) $r->avg_order_value,
            'repeat_rate'     => (float) $r->repeat_rate,
        ], $rows);
    }

    private function getCohortLtv(int $shopId): array
    {
        $sql = "
            WITH customer_first AS (
                SELECT customer_pancake_id, MIN(ordered_at) AS first_at
                FROM orders
                WHERE shop_id = ? AND customer_pancake_id IS NOT NULL AND ordered_at IS NOT NULL
                GROUP BY customer_pancake_id
            ),
            customer_entry AS (
                SELECT cf.customer_pancake_id,
                       cf.first_at,
                       LOWER(json_extract(item.value, '$.variation_info.name')) AS entry_product
                FROM customer_first cf
                JOIN orders o ON o.customer_pancake_id = cf.customer_pancake_id
                             AND o.ordered_at = cf.first_at
                             AND o.shop_id = ?
                JOIN json_each(o.items) AS item
                WHERE json_extract(item.value, '$.variation_info.name') IS NOT NULL
                GROUP BY cf.customer_pancake_id
            ),
            customer_period AS (
                SELECT ce.customer_pancake_id,
                       ce.entry_product,
                       SUM(CASE WHEN o.status='delivered' AND julianday(o.ordered_at)-julianday(ce.first_at)<=30  THEN o.total_price ELSE 0 END) AS rev_30,
                       SUM(CASE WHEN o.status='delivered' AND julianday(o.ordered_at)-julianday(ce.first_at)<=60  THEN o.total_price ELSE 0 END) AS rev_60,
                       SUM(CASE WHEN o.status='delivered' AND julianday(o.ordered_at)-julianday(ce.first_at)<=90  THEN o.total_price ELSE 0 END) AS rev_90,
                       SUM(CASE WHEN o.status='delivered' AND julianday(o.ordered_at)-julianday(ce.first_at)<=120 THEN o.total_price ELSE 0 END) AS rev_120,
                       SUM(CASE WHEN o.status='delivered' AND julianday(o.ordered_at)-julianday(ce.first_at)<=150 THEN o.total_price ELSE 0 END) AS rev_150,
                       SUM(CASE WHEN o.status='delivered' AND julianday(o.ordered_at)-julianday(ce.first_at)<=180 THEN o.total_price ELSE 0 END) AS rev_180
                FROM customer_entry ce
                JOIN orders o ON o.customer_pancake_id = ce.customer_pancake_id AND o.shop_id = ?
                GROUP BY ce.customer_pancake_id, ce.entry_product
            )
            SELECT entry_product          AS product,
                   COUNT(*)              AS customer_count,
                   ROUND(AVG(rev_30),0)  AS ltv_30,
                   ROUND(AVG(rev_60),0)  AS ltv_60,
                   ROUND(AVG(rev_90),0)  AS ltv_90,
                   ROUND(AVG(rev_120),0) AS ltv_120,
                   ROUND(AVG(rev_150),0) AS ltv_150,
                   ROUND(AVG(rev_180),0) AS ltv_180
            FROM customer_period
            GROUP BY entry_product
            HAVING customer_count >= 10
            ORDER BY ltv_180 DESC
            LIMIT 6
        ";

        $rows = DB::select($sql, [$shopId, $shopId, $shopId]);
        return array_map(fn($r) => [
            'product'        => $r->product,
            'customer_count' => (int)   $r->customer_count,
            'ltv_30'         => (float) $r->ltv_30,
            'ltv_60'         => (float) $r->ltv_60,
            'ltv_90'         => (float) $r->ltv_90,
            'ltv_120'        => (float) $r->ltv_120,
            'ltv_150'        => (float) $r->ltv_150,
            'ltv_180'        => (float) $r->ltv_180,
        ], $rows);
    }

    private function getMonthlyTrend(int $shopId): array
    {
        $sql = "
            WITH customer_first AS (
                SELECT customer_pancake_id, MIN(ordered_at) AS first_at
                FROM orders
                WHERE shop_id = ? AND customer_pancake_id IS NOT NULL AND ordered_at IS NOT NULL
                GROUP BY customer_pancake_id
            ),
            customer_entry AS (
                SELECT cf.customer_pancake_id,
                       LOWER(json_extract(item.value, '$.variation_info.name')) AS entry_product,
                       strftime('%Y-%m', cf.first_at) AS month
                FROM customer_first cf
                JOIN orders o ON o.customer_pancake_id = cf.customer_pancake_id
                             AND o.ordered_at = cf.first_at
                             AND o.shop_id = ?
                JOIN json_each(o.items) AS item
                WHERE json_extract(item.value, '$.variation_info.name') IS NOT NULL
                GROUP BY cf.customer_pancake_id
            )
            SELECT entry_product AS product,
                   month,
                   COUNT(*) AS new_customers
            FROM customer_entry
            WHERE month >= strftime('%Y-%m', date('now', '-11 months'))
            GROUP BY entry_product, month
            ORDER BY entry_product, month
        ";

        $rows = DB::select($sql, [$shopId, $shopId]);
        return array_map(fn($r) => [
            'product'       => $r->product,
            'month'         => $r->month,
            'new_customers' => (int) $r->new_customers,
        ], $rows);
    }

    private function getNewCustomerRts(int $shopId): array
    {
        $sql = "
            WITH customer_first AS (
                SELECT customer_pancake_id, MIN(ordered_at) AS first_at
                FROM orders
                WHERE shop_id = ? AND customer_pancake_id IS NOT NULL AND ordered_at IS NOT NULL
                GROUP BY customer_pancake_id
            ),
            customer_entry AS (
                SELECT cf.customer_pancake_id,
                       LOWER(json_extract(item.value, '$.variation_info.name')) AS entry_product,
                       o.status AS first_status
                FROM customer_first cf
                JOIN orders o ON o.customer_pancake_id = cf.customer_pancake_id
                             AND o.ordered_at = cf.first_at
                             AND o.shop_id = ?
                JOIN json_each(o.items) AS item
                WHERE json_extract(item.value, '$.variation_info.name') IS NOT NULL
                GROUP BY cf.customer_pancake_id
            )
            SELECT
                entry_product AS product,
                COUNT(*) AS total_new_customers,
                ROUND(
                    SUM(CASE WHEN first_status IN ('returned_to_sender','cancelled') THEN 1.0 ELSE 0 END)
                    / COUNT(*) * 100, 1
                ) AS rts_rate
            FROM customer_entry
            GROUP BY entry_product
            HAVING total_new_customers >= 3
        ";

        $rows = DB::select($sql, [$shopId, $shopId]);
        return array_map(fn($r) => [
            'product'             => $r->product,
            'total_new_customers' => (int)   $r->total_new_customers,
            'rts_rate'            => (float) $r->rts_rate,
        ], $rows);
    }
}
