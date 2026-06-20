<?php
namespace App\Http\Controllers;

use App\Models\Customer;
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
        $cached = Cache::remember($cacheKey, 300, function () use ($shop, $dateFrom, $dateTo, $productFilter) {
            return [
                'totalCustomers'   => $this->baseCustomer($shop->id, $dateFrom, $dateTo, $productFilter)->count(),
                'genderStats'      => $this->getGenderStats($shop->id, $dateFrom, $dateTo, $productFilter),
                'ageStats'         => $this->getOrderFrequencyStats($shop->id, $dateFrom, $dateTo, $productFilter),
                'birthdayMonth'    => $this->getAcquisitionByMonthStats($shop->id, $dateFrom, $dateTo, $productFilter),
                'topProvinces'     => $this->getTopProvinces($shop->id, $dateFrom, $dateTo, $productFilter),
                'newVsReturning'   => $this->getNewVsReturning($shop->id, $dateFrom, $dateTo, $productFilter),
                'topCustomers'     => $this->getTopCustomers($shop->id, $dateFrom, $dateTo, $productFilter),
                'peakPatterns'     => $this->getPeakOrderPatterns($shop->id, $dateFrom, $dateTo, $productFilter),
                'basketSize'       => $this->getBasketSize($shop->id, $dateFrom, $dateTo, $productFilter),
                'productAffinity'  => $this->getProductAffinity($shop->id, $dateFrom, $dateTo),
                'firstProducts'    => $this->getFirstProductPurchased($shop->id),
            ];
        });

        $totalCustomers  = $cached['totalCustomers'];
        $genderStats     = $cached['genderStats'];
        $ageStats        = $cached['ageStats'];
        $birthdayMonth   = $cached['birthdayMonth'];
        $topProvinces    = $cached['topProvinces'];
        $newVsReturning  = $cached['newVsReturning'];
        $topCustomers    = $cached['topCustomers'];
        $peakPatterns    = $cached['peakPatterns'];
        $basketSize      = $cached['basketSize'];
        $productAffinity = $cached['productAffinity'];
        $firstProducts   = $cached['firstProducts'];

        return view('analytics.customers', compact(
            'shop', 'totalCustomers', 'genderStats', 'ageStats',
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
        if ($dateFrom && $dateTo) {
            $q->whereBetween('ordered_at', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);
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
        $rows = $this->baseCustomer($shopId, $dateFrom, $dateTo, $productFilter)
            ->select('gender', DB::raw('COUNT(*) as count'))
            ->groupBy('gender')
            ->get();

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
        $q = DB::table('orders')
            ->where('shop_id', $shopId)
            ->whereNotNull('customer_pancake_id')
            ->select('customer_pancake_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('customer_pancake_id');
        $this->applyOrderFilters($q, $dateFrom, $dateTo, $productFilter);

        $dist   = $q->get();
        $groups = ['1 order' => 0, '2 orders' => 0, '3 orders' => 0, '4 orders' => 0, '5+ orders' => 0];
        foreach ($dist as $row) {
            if ($row->cnt == 1)     $groups['1 order']++;
            elseif ($row->cnt == 2) $groups['2 orders']++;
            elseif ($row->cnt == 3) $groups['3 orders']++;
            elseif ($row->cnt == 4) $groups['4 orders']++;
            else                    $groups['5+ orders']++;
        }
        return ['labels' => array_keys($groups), 'data' => array_values($groups)];
    }

    private function getAcquisitionByMonthStats(int $shopId, ?string $dateFrom, ?string $dateTo, ?string $productFilter): array
    {
        $q = DB::table('orders')
            ->where('shop_id', $shopId)
            ->whereNotNull('customer_pancake_id')
            ->whereNotNull('ordered_at')
            ->selectRaw('customer_pancake_id, MIN(ordered_at) as first_order')
            ->groupBy('customer_pancake_id');
        $this->applyOrderFilters($q, $dateFrom, $dateTo, $productFilter);

        $byMonth = [];
        foreach ($q->get() as $row) {
            $m = (int) date('n', strtotime($row->first_order));
            $byMonth[$m] = ($byMonth[$m] ?? 0) + 1;
        }

        $labels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $data   = [];
        for ($m = 1; $m <= 12; $m++) {
            $data[] = $byMonth[$m] ?? 0;
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
        $q = DB::table('orders')
            ->where('shop_id', $shopId)
            ->whereNotNull('customer_pancake_id')
            ->select('customer_pancake_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('customer_pancake_id');
        $this->applyOrderFilters($q, $dateFrom, $dateTo, $productFilter);

        $dist = $q->get();
        return [
            'new'       => $dist->where('cnt', 1)->count(),
            'returning' => $dist->where('cnt', '>', 1)->count(),
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
            ->selectRaw("CAST(strftime('%w', ordered_at) AS INTEGER) as dow, COUNT(*) as cnt")
            ->groupByRaw("strftime('%w', ordered_at)")
            ->get()->keyBy('dow');

        $hourRows = (clone $base)
            ->selectRaw("CAST(strftime('%H', ordered_at) AS INTEGER) as hr, COUNT(*) as cnt")
            ->groupByRaw("strftime('%H', ordered_at)")
            ->get()->keyBy('hr');

        $dayData    = array_map(fn($d) => (int)($dayRows->get($d)?->cnt  ?? 0), range(0, 6));
        $hourLabels = array_map(fn($h) => str_pad($h, 2, '0', STR_PAD_LEFT) . ':00', range(0, 23));
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

        if ($dateFrom && $dateTo) {
            $whereParts[] = 'o.ordered_at BETWEEN ? AND ?';
            $params[]     = $dateFrom . ' 00:00:00';
            $params[]     = $dateTo   . ' 23:59:59';
        }

        if ($productFilter) {
            $whereParts[] = "EXISTS (SELECT 1 FROM json_each(o.items) AS pf WHERE json_extract(pf.value, '$.variation_info.name') = ? COLLATE NOCASE)";
            $params[]     = $productFilter;
        }

        $where = implode(' AND ', $whereParts);

        $innerSql = "
            SELECT o.id,
                   SUM(
                       MAX(CAST(json_extract(item.value, '$.variation_info.name') AS INTEGER), 1)
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
        $whereParts = ['o.shop_id = ?'];
        $params     = [$shopId];

        if ($dateFrom && $dateTo) {
            $whereParts[] = 'o.ordered_at BETWEEN ? AND ?';
            $params[]     = $dateFrom . ' 00:00:00';
            $params[]     = $dateTo   . ' 23:59:59';
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
        $sql = "SELECT LOWER(json_extract(item.value, '$.variation_info.name')) as product_name,
                       COUNT(DISTINCT o.customer_pancake_id) as first_buyers
                FROM orders o, json_each(o.items) as item
                WHERE o.shop_id = ?
                  AND o.customer_pancake_id IS NOT NULL
                  AND json_extract(item.value, '$.variation_info.name') IS NOT NULL
                  AND o.ordered_at = (
                      SELECT MIN(o2.ordered_at) FROM orders o2
                      WHERE o2.shop_id = ? AND o2.customer_pancake_id = o.customer_pancake_id
                  )
                GROUP BY product_name
                ORDER BY first_buyers DESC
                LIMIT 10";

        return array_map(
            fn($r) => ['product_name' => $r->product_name, 'first_buyers' => $r->first_buyers],
            DB::select($sql, [$shopId, $shopId])
        );
    }
}
