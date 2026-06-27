<?php
namespace App\Http\Controllers;

use App\Models\Customer;
use App\Support\DemographicsExtractor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CustomerAnalyticsController extends Controller
{
    public function index(Request $request)
    {
        $shop          = $this->user()->shops()->where('is_active', true)->firstOrFail();
        $dateFrom      = $request->input('date_from', now()->subDays(30)->format('Y-m-d')) ?: null;
        $dateTo        = $request->input('date_to',   now()->format('Y-m-d'))              ?: null;
        $productFilter = $request->input('product_filter');
        $products      = $this->getProducts($shop->id);
        $datePreset    = $this->detectDatePreset($dateFrom, $dateTo);

        $cacheKey = "customers_{$shop->id}_{$this->shopCacheBust($shop->id)}_{$dateFrom}_{$dateTo}_{$productFilter}";
        $cached = Cache::remember($cacheKey, 1800, function () use ($shop, $dateFrom, $dateTo, $productFilter) {
            // Compute new/returning first so totalCustomers uses the same source (orders table).
            // The customers table may be out of sync with orders, causing new+returning > total.
            $newVsReturning = $this->getNewVsReturning($shop->id, $dateFrom, $dateTo, $productFilter);
            return [
                'totalCustomers'   => $newVsReturning['new'] + $newVsReturning['returning'],
                'genderStats'      => $this->getGenderStats($shop->id, $dateFrom, $dateTo, $productFilter),
                'ageStats'         => $this->getOrderFrequencyStats($shop->id, $dateFrom, $dateTo, $productFilter),
                'ageGroups'        => $this->getAgeGroups($shop->id, $dateFrom, $dateTo, $productFilter),
                'healthConditions' => $this->getHealthConditions($shop->id, $dateFrom, $dateTo, $productFilter),
                'birthdayMonth'    => $this->getAcquisitionByMonthStats($shop->id, $dateFrom, $dateTo, $productFilter),
                'topProvinces'     => $this->getTopProvinces($shop->id, $dateFrom, $dateTo, $productFilter),
                'newVsReturning'   => $newVsReturning,
                'topCustomers'     => $this->getTopCustomers($shop->id, $dateFrom, $dateTo, $productFilter),
                'peakPatterns'     => $this->getPeakOrderPatterns($shop->id, $dateFrom, $dateTo, $productFilter),
                'basketSize'       => $this->getBasketSize($shop->id, $dateFrom, $dateTo, $productFilter),
                'productAffinity'  => $this->getProductAffinity($shop->id, $dateFrom, $dateTo),
                'firstProducts'    => $this->getFirstProductPurchased($shop->id),
            ];
        });

        $totalCustomers   = $cached['totalCustomers'];
        $genderStats      = $cached['genderStats'];
        $ageStats         = $cached['ageStats'];
        $ageGroups        = $cached['ageGroups'];
        $healthConditions = $cached['healthConditions'];
        $birthdayMonth    = $cached['birthdayMonth'];
        $topProvinces    = $cached['topProvinces'];
        $newVsReturning  = $cached['newVsReturning'];
        $topCustomers    = $cached['topCustomers'];
        $peakPatterns    = $cached['peakPatterns'];
        $basketSize      = $cached['basketSize'];
        $productAffinity = $cached['productAffinity'];
        $firstProducts   = $cached['firstProducts'];

        if ($request->wantsJson()) {
            $newPct = $totalCustomers > 0 ? round($newVsReturning['new'] / $totalCustomers * 100) : 0;
            return response()->json([
                'kpis' => [
                    'totalCustomers' => $totalCustomers,
                    'newCustomers'   => $newVsReturning['new'],
                    'returning'      => $newVsReturning['returning'],
                    'newPct'         => $newPct,
                    'retPct'         => 100 - $newPct,
                    'basketAvg'      => $basketSize['avg'],
                ],
                'genderStats'      => $genderStats,
                'ageStats'         => $ageStats,
                'ageGroups'        => $ageGroups,
                'healthConditions' => $healthConditions,
                'birthdayMonth'    => $birthdayMonth,
                'peakDays'        => $peakPatterns['days'],
                'peakHours'       => $peakPatterns['hours'],
                'basketDist'      => $basketSize['distribution'],
                'topProvinces'    => $topProvinces,
                'topCustomers'    => $topCustomers,
                'firstProducts'   => $firstProducts,
                'productAffinity' => $productAffinity,
            ]);
        }

        return view('analytics.customers', compact(
            'shop', 'totalCustomers', 'genderStats', 'ageStats', 'ageGroups', 'healthConditions',
            'birthdayMonth', 'topProvinces', 'newVsReturning', 'topCustomers',
            'peakPatterns', 'basketSize', 'productAffinity', 'firstProducts',
            'products', 'productFilter', 'dateFrom', 'dateTo', 'datePreset'
        ));
    }

    // Returns a customer query scoped to those who placed orders matching the filters.
    // Uses a subquery instead of loading IDs into PHP to avoid memory blowout on large datasets.
    private function baseCustomer(int $shopId, ?string $dateFrom, ?string $dateTo, ?string $productFilter): \Illuminate\Database\Eloquent\Builder
    {
        $q = Customer::query()->where('shop_id', $shopId);

        if ($dateFrom || $dateTo || $productFilter) {
            $q->whereIn('pancake_id', function ($sub) use ($shopId, $dateFrom, $dateTo, $productFilter) {
                $sub->from('orders')
                    ->select('customer_pancake_id')
                    ->where('shop_id', $shopId)
                    ->whereNotNull('customer_pancake_id');
                $this->applyOrderFilters($sub, $dateFrom, $dateTo, $productFilter);
            });
        }

        return $q;
    }

    // Applies date + product filters directly to an orders query builder (Eloquent or DB).
    private function applyOrderFilters($q, ?string $dateFrom, ?string $dateTo, ?string $productFilter): void
    {
        $effectiveTo = ($dateTo ?? now()->format('Y-m-d')) . ' 23:59:59';
        if ($dateFrom) {
            $q->whereBetween('ordered_at', [$dateFrom . ' 00:00:00', $effectiveTo]);
        } else {
            $q->where('ordered_at', '<=', $effectiveTo);
        }
        if ($productFilter) {
            $q->whereRaw(
                "EXISTS (SELECT 1 FROM json_each(items) as item WHERE json_extract(item.value, '$.variation_info.name') = ? COLLATE NOCASE)",
                [$productFilter]
            );
        }
    }

    private function getGenderStats(int $shopId, ?string $dateFrom, ?string $dateTo, ?string $productFilter): array
    {
        // Use orders as the source of truth (same base as totalCustomers) and LEFT JOIN
        // customers for gender so that customers missing from the customers table are
        // still counted (they land in Unknown instead of being silently excluded).
        $q = DB::table('orders as o')
            ->where('o.shop_id', $shopId)
            ->whereNotNull('o.customer_pancake_id')
            ->leftJoin('customers as c', function ($join) use ($shopId) {
                $join->on('c.pancake_id', '=', 'o.customer_pancake_id')
                     ->where('c.shop_id', '=', $shopId);
            })
            ->select('c.gender', DB::raw('COUNT(DISTINCT o.customer_pancake_id) as count'))
            ->groupBy('c.gender');

        $genderEffectiveTo = ($dateTo ?? now()->format('Y-m-d')) . ' 23:59:59';
        if ($dateFrom) {
            $q->whereBetween('o.ordered_at', [$dateFrom . ' 00:00:00', $genderEffectiveTo]);
        } else {
            $q->where('o.ordered_at', '<=', $genderEffectiveTo);
        }
        if ($productFilter) {
            $q->whereRaw(
                "EXISTS (SELECT 1 FROM json_each(o.items) as item WHERE json_extract(item.value, '$.variation_info.name') = ? COLLATE NOCASE)",
                [$productFilter]
            );
        }

        $rows = $q->get();

        $normalized = [];
        foreach ($rows as $row) {
            $key = match (strtolower(trim((string)($row->gender ?? '')))) {
                'male', 'lalaki'  => 'Male',
                'female', 'babae' => 'Female',
                default           => 'Unknown',
            };
            $normalized[$key] = ($normalized[$key] ?? 0) + $row->count;
        }
        arsort($normalized);
        return ['labels' => array_keys($normalized), 'data' => array_values($normalized)];
    }

    private function getOrderFrequencyStats(int $shopId, ?string $dateFrom, ?string $dateTo, ?string $productFilter): array
    {
        $sub = DB::table('orders')
            ->where('shop_id', $shopId)
            ->whereNotNull('customer_pancake_id')
            ->select('customer_pancake_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('customer_pancake_id');
        $this->applyOrderFilters($sub, $dateFrom, $dateTo, $productFilter);

        $row = DB::table($sub, 'sub')->selectRaw(
            'SUM(CASE WHEN cnt = 1 THEN 1 ELSE 0 END) as c1, ' .
            'SUM(CASE WHEN cnt = 2 THEN 1 ELSE 0 END) as c2, ' .
            'SUM(CASE WHEN cnt = 3 THEN 1 ELSE 0 END) as c3, ' .
            'SUM(CASE WHEN cnt = 4 THEN 1 ELSE 0 END) as c4, ' .
            'SUM(CASE WHEN cnt >= 5 THEN 1 ELSE 0 END) as c5plus'
        )->first();

        $groups = [
            '1 order'   => (int) ($row->c1    ?? 0),
            '2 orders'  => (int) ($row->c2    ?? 0),
            '3 orders'  => (int) ($row->c3    ?? 0),
            '4 orders'  => (int) ($row->c4    ?? 0),
            '5+ orders' => (int) ($row->c5plus ?? 0),
        ];
        return ['labels' => array_keys($groups), 'data' => array_values($groups)];
    }

    private function getAcquisitionByMonthStats(int $shopId, ?string $dateFrom, ?string $dateTo, ?string $productFilter): array
    {
        $sub = DB::table('orders')
            ->where('shop_id', $shopId)
            ->whereNotNull('customer_pancake_id')
            ->whereNotNull('ordered_at')
            ->selectRaw('customer_pancake_id, MIN(ordered_at) as first_order')
            ->groupBy('customer_pancake_id');
        $this->applyOrderFilters($sub, $dateFrom, $dateTo, $productFilter);

        $byMonth = DB::table($sub, 'sub')
            ->selectRaw("CAST(strftime('%m', first_order) AS INTEGER) as month, COUNT(*) as cnt")
            ->groupByRaw("strftime('%m', first_order)")
            ->pluck('cnt', 'month');

        $labels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $data   = [];
        for ($m = 1; $m <= 12; $m++) {
            $data[] = (int) ($byMonth[$m] ?? 0);
        }
        return ['labels' => $labels, 'data' => $data];
    }

    private function getTopProvinces(int $shopId, ?string $dateFrom, ?string $dateTo, ?string $productFilter): array
    {
        // Use orders.province (structured from Pancake's shipping_address.province_name),
        // counting distinct customers per province so repeat orders don't skew the list.
        $q = DB::table('orders')
            ->where('shop_id', $shopId)
            ->whereNotNull('province')
            ->whereNotNull('customer_pancake_id');
        $this->applyOrderFilters($q, $dateFrom, $dateTo, $productFilter);
        return $q->select(
                'province',
                DB::raw('COUNT(DISTINCT customer_pancake_id) as count'),
                DB::raw("SUM(CASE WHEN status = 'delivered' THEN total_price ELSE 0 END) as total_spent")
            )
            ->groupBy('province')
            ->orderByDesc('count')
            ->take(15)
            ->get()
            ->map(fn($r) => (array) $r)
            ->toArray();
    }

    private function getNewVsReturning(int $shopId, ?string $dateFrom, ?string $dateTo, ?string $productFilter): array
    {
        $sub = DB::table('orders')
            ->where('shop_id', $shopId)
            ->whereNotNull('customer_pancake_id')
            ->select('customer_pancake_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('customer_pancake_id');
        $this->applyOrderFilters($sub, $dateFrom, $dateTo, $productFilter);

        $row = DB::table($sub, 'sub')->selectRaw(
            'SUM(CASE WHEN cnt = 1 THEN 1 ELSE 0 END) as new_count, ' .
            'SUM(CASE WHEN cnt > 1 THEN 1 ELSE 0 END) as ret_count'
        )->first();

        return [
            'new'       => (int) ($row->new_count ?? 0),
            'returning' => (int) ($row->ret_count ?? 0),
        ];
    }

    private function getTopCustomers(int $shopId, ?string $dateFrom, ?string $dateTo, ?string $productFilter): array
    {
        return $this->baseCustomer($shopId, $dateFrom, $dateTo, $productFilter)
            ->orderByDesc('total_spent')
            ->take(20)
            ->get(['name', 'phone', 'province', 'customer_level', 'total_orders', 'total_spent'])
            ->toArray();
    }

    private function getPeakOrderPatterns(int $shopId, ?string $dateFrom, ?string $dateTo, ?string $productFilter): array
    {
        $dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

        $base = DB::table('orders')->where('shop_id', $shopId)->whereNotNull('ordered_at');
        $this->applyOrderFilters($base, $dateFrom, $dateTo, $productFilter);

        $dayRows  = (clone $base)
            ->selectRaw("CAST(strftime('%w', ordered_at, '+8 hours') AS INTEGER) as dow, COUNT(*) as cnt")
            ->groupByRaw("strftime('%w', ordered_at, '+8 hours')")
            ->get()->keyBy('dow');

        $hourRows = (clone $base)
            ->selectRaw("CAST(strftime('%H', ordered_at, '+8 hours') AS INTEGER) as hr, COUNT(*) as cnt")
            ->groupByRaw("strftime('%H', ordered_at, '+8 hours')")
            ->get()->keyBy('hr');

        $dayData    = array_map(fn($d) => (int)($dayRows->get($d)?->cnt  ?? 0), range(0, 6));
        $hourLabels = array_map(function($h) {
            if ($h === 0)  return '12am';
            if ($h < 12)   return $h . 'am';
            if ($h === 12) return '12pm';
            return ($h - 12) . 'pm';
        }, range(0, 23));
        $hourData   = array_map(fn($h) => (int)($hourRows->get($h)?->cnt ?? 0), range(0, 23));

        return [
            'days'  => ['labels' => $dayNames,   'data' => $dayData],
            'hours' => ['labels' => $hourLabels,  'data' => $hourData],
        ];
    }

    private function getBasketSize(int $shopId, ?string $dateFrom, ?string $dateTo, ?string $productFilter): array
    {
        // Each variation name encodes the bottle count as a leading integer
        // (e.g. "4 Clear Sight 3.0" = 4 bottles). CAST(name AS INTEGER) extracts
        // that number; MAX(..., 1) treats non-numeric names as 1 unit.
        // Multiply by $.quantity in case the customer ordered multiple of the same SKU.
        $whereParts = ['o.shop_id = ?'];
        $params     = [$shopId];

        $basketEffectiveTo = ($dateTo ?? now()->format('Y-m-d')) . ' 23:59:59';
        if ($dateFrom) {
            $whereParts[] = 'o.ordered_at BETWEEN ? AND ?';
            $params[]     = $dateFrom . ' 00:00:00';
            $params[]     = $basketEffectiveTo;
        } else {
            $whereParts[] = 'o.ordered_at <= ?';
            $params[]     = $basketEffectiveTo;
        }

        if ($productFilter) {
            $whereParts[] = "EXISTS (SELECT 1 FROM json_each(o.items) AS pf WHERE json_extract(pf.value, '$.variation_info.name') = ? COLLATE NOCASE)";
            $params[]     = $productFilter;
        }

        $where = implode(' AND ', $whereParts);

        $innerSql = "
            SELECT o.id,
                   SUM(
                       MAX(CAST(json_extract(item.value, '$.variation_info.display_id') AS INTEGER), 1)
                       * MAX(CAST(COALESCE(json_extract(item.value, '$.quantity'), 1) AS INTEGER), 1)
                   ) AS bottle_count
            FROM orders o, json_each(o.items) AS item
            WHERE {$where}
              AND o.items IS NOT NULL AND o.items != '[]'
            GROUP BY o.id
        ";

        $rows    = DB::select("SELECT bottle_count, COUNT(*) as order_count FROM ({$innerSql}) GROUP BY bottle_count ORDER BY bottle_count", $params);
        $buckets = ['1' => 0, '2' => 0, '3' => 0, '4' => 0, '5+' => 0];
        $total   = 0;
        $count   = 0;

        foreach ($rows as $row) {
            $bc = (int) $row->bottle_count;
            $oc = (int) $row->order_count;
            $total += $bc * $oc;
            $count += $oc;
            $k = $bc >= 5 ? '5+' : (string) $bc;
            $buckets[$k] = ($buckets[$k] ?? 0) + $oc;
        }

        return [
            'avg'          => $count > 0 ? round($total / $count, 1) : 0,
            'distribution' => ['labels' => array_keys($buckets), 'data' => array_values($buckets)],
        ];
    }

    private function getProductAffinity(int $shopId, ?string $dateFrom, ?string $dateTo): array
    {
        // Restrict to delivered orders: reduces the working set for the self-join
        // and makes affinity results reflect actual purchases, not cancelled/RTS orders.
        $whereParts = ["o.shop_id = ?", "o.status = 'delivered'"];
        $params     = [$shopId];

        $affinityEffectiveTo = ($dateTo ?? now()->format('Y-m-d')) . ' 23:59:59';
        if ($dateFrom) {
            $whereParts[] = 'o.ordered_at BETWEEN ? AND ?';
            $params[]     = $dateFrom . ' 00:00:00';
            $params[]     = $affinityEffectiveTo;
        } else {
            $whereParts[] = 'o.ordered_at <= ?';
            $params[]     = $affinityEffectiveTo;
        }

        $where = implode(' AND ', $whereParts);

        $sub = "SELECT o.id as oid, LOWER(json_extract(item.value, '$.variation_info.name')) as pname
                FROM orders o, json_each(o.items) as item
                WHERE {$where} AND json_extract(item.value, '$.variation_info.name') IS NOT NULL";

        $sql = "SELECT a.pname as product_a, b.pname as product_b, COUNT(*) as pair_count
                FROM ({$sub}) a
                JOIN ({$sub}) b ON a.oid = b.oid AND a.pname < b.pname
                GROUP BY a.pname, b.pname
                ORDER BY pair_count DESC
                LIMIT 10";

        return array_map(
            fn($r) => ['product_a' => $r->product_a, 'product_b' => $r->product_b, 'pair_count' => $r->pair_count],
            DB::select($sql, array_merge($params, $params))
        );
    }

    private function getFirstProductPurchased(int $shopId): array
    {
        // CTE pre-aggregates each customer's first order in one pass using the
        // orders_shop_customer_date index, then joins back — avoids a correlated
        // subquery that would fire once per order row.
        $sql = "
            WITH first_orders AS (
                SELECT customer_pancake_id, MIN(ordered_at) AS first_ordered_at
                FROM orders
                WHERE shop_id = ?
                  AND customer_pancake_id IS NOT NULL
                GROUP BY customer_pancake_id
            )
            SELECT LOWER(json_extract(item.value, '$.variation_info.name')) AS product_name,
                   COUNT(DISTINCT o.customer_pancake_id) AS first_buyers
            FROM first_orders fo
            JOIN orders o ON o.customer_pancake_id = fo.customer_pancake_id
                         AND o.ordered_at = fo.first_ordered_at
                         AND o.shop_id = ?,
                 json_each(o.items) AS item
            WHERE json_extract(item.value, '$.variation_info.name') IS NOT NULL
            GROUP BY product_name
            ORDER BY first_buyers DESC
            LIMIT 10
        ";

        return array_map(
            fn($r) => ['product_name' => $r->product_name, 'first_buyers' => $r->first_buyers],
            DB::select($sql, [$shopId, $shopId])
        );
    }

    private function getAgeGroups(int $shopId, ?string $from, ?string $to, ?string $product): array
    {
        $q = DB::table('orders')
            ->where('shop_id', $shopId)
            ->whereNotNull('customer_age')
            ->where('customer_age', '>', 0);
        if ($from) $q->where('ordered_at', '>=', $from . ' 00:00:00');
        $q->where('ordered_at', '<=', ($to ?? now()->format('Y-m-d')) . ' 23:59:59');
        if ($product) {
            $q->whereRaw(
                "EXISTS (SELECT 1 FROM json_each(items) as item WHERE json_extract(item.value, '$.variation_info.name') = ? COLLATE NOCASE)",
                [$product]
            );
        }

        $rows = $q->selectRaw("
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

    private function getHealthConditions(int $shopId, ?string $from, ?string $to, ?string $product): array
    {
        $bindings = [$shopId];
        $productWhere = '';
        if ($product) {
            $productWhere = "AND EXISTS (SELECT 1 FROM json_each(o.items) AS item WHERE json_extract(item.value, '$.variation_info.name') = ? COLLATE NOCASE)";
            $bindings[] = $product;
        }
        $dateWhere = '';
        if ($from) { $dateWhere .= " AND o.ordered_at >= ?"; $bindings[] = $from . ' 00:00:00'; }
        $dateWhere .= " AND o.ordered_at <= ?"; $bindings[] = ($to ?? now()->format('Y-m-d')) . ' 23:59:59';

        $rows = DB::select("
            SELECT je.value AS condition, COUNT(*) AS cnt
            FROM orders o, json_each(o.health_condition) AS je
            WHERE o.shop_id = ?
              AND o.health_condition IS NOT NULL
              AND o.health_condition LIKE '[%'
              {$productWhere}
              {$dateWhere}
            GROUP BY je.value
        ", $bindings);

        $counts = [];
        foreach ($rows as $r) {
            $counts[$r->condition] = (int) $r->cnt;
        }

        $q = DB::table('orders as o')
            ->where('o.shop_id', $shopId)
            ->whereNotNull('o.extra_note')
            ->where(function ($q) {
                $q->whereNull('o.health_condition')
                  ->orWhereRaw("o.health_condition NOT LIKE '[%'");
            });
        if ($product) {
            $q->whereRaw(
                "EXISTS (SELECT 1 FROM json_each(o.items) AS item WHERE json_extract(item.value, '$.variation_info.name') = ? COLLATE NOCASE)",
                [$product]
            );
        }
        if ($from) $q->where('o.ordered_at', '>=', $from . ' 00:00:00');
        $q->where('o.ordered_at', '<=', ($to ?? now()->format('Y-m-d')) . ' 23:59:59');

        $scanned = 0;
        $q->select('o.id', 'o.extra_note')->orderBy('o.id')->chunk(500, function ($orders) use (&$counts, &$scanned) {
            foreach ($orders as $order) {
                foreach (DemographicsExtractor::conditions($order->extra_note) as $cond) {
                    $counts[$cond] = ($counts[$cond] ?? 0) + 1;
                }
            }
            $scanned += $orders->count();
            if ($scanned >= 5000) return false;
        });

        if ($product) {
            $category = DemographicsExtractor::categoryForProduct($product);
            $allowed  = DemographicsExtractor::labelsForCategory($category);
            if ($allowed !== null) {
                $counts = array_intersect_key($counts, array_flip($allowed));
            }
        }

        arsort($counts);
        $top = array_slice($counts, 0, 12, true);
        return ['labels' => array_keys($top), 'data' => array_values($top)];
    }
}
