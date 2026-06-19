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

        $cacheKey = "customers_{$shop->id}_{$dateFrom}_{$dateTo}_{$productFilter}";
        $cached = Cache::remember($cacheKey, 300, function () use ($shop, $dateFrom, $dateTo, $productFilter) {
            return [
                'totalCustomers' => $this->baseCustomer($shop->id, $dateFrom, $dateTo, $productFilter)->count(),
                'genderStats'    => $this->getGenderStats($shop->id, $dateFrom, $dateTo, $productFilter),
                'ageStats'       => $this->getOrderFrequencyStats($shop->id, $dateFrom, $dateTo, $productFilter),
                'levelStats'     => $this->getCustomerLevelStats($shop->id, $dateFrom, $dateTo, $productFilter),
                'birthdayMonth'  => $this->getAcquisitionByMonthStats($shop->id, $dateFrom, $dateTo, $productFilter),
                'topProvinces'   => $this->getTopProvinces($shop->id, $dateFrom, $dateTo, $productFilter),
                'newVsReturning' => $this->getNewVsReturning($shop->id, $dateFrom, $dateTo, $productFilter),
                'topCustomers'   => $this->getTopCustomers($shop->id, $dateFrom, $dateTo, $productFilter),
            ];
        });

        $totalCustomers = $cached['totalCustomers'];
        $genderStats    = $cached['genderStats'];
        $ageStats       = $cached['ageStats'];
        $levelStats     = $cached['levelStats'];
        $birthdayMonth  = $cached['birthdayMonth'];
        $topProvinces   = $cached['topProvinces'];
        $newVsReturning = $cached['newVsReturning'];
        $topCustomers   = $cached['topCustomers'];

        return view('analytics.customers', compact(
            'shop', 'totalCustomers', 'genderStats', 'ageStats', 'levelStats',
            'birthdayMonth', 'topProvinces', 'newVsReturning', 'topCustomers',
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

    private function getCustomerLevelStats(int $shopId, ?string $dateFrom, ?string $dateTo, ?string $productFilter): array
    {
        return $this->baseCustomer($shopId, $dateFrom, $dateTo, $productFilter)
            ->whereNotNull('customer_level')
            ->select('customer_level', DB::raw('COUNT(*) as count'), DB::raw('AVG(total_spent) as avg_spent'))
            ->groupBy('customer_level')
            ->orderByDesc('count')
            ->get()
            ->toArray();
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
        return $this->baseCustomer($shopId, $dateFrom, $dateTo, $productFilter)
            ->whereNotNull('province')
            ->select('province', DB::raw('COUNT(*) as count'), DB::raw('SUM(total_spent) as total_spent'))
            ->groupBy('province')
            ->orderByDesc('count')
            ->take(15)
            ->get()
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
}
