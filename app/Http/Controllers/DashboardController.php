<?php
namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Order;
use App\Models\PancakeShop;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $shop = $this->user()->shops()->where('is_active', true)->first();

        if (!$shop) {
            return redirect()->route('settings.index')
                ->with('info', 'Please connect your Pancake POS API first.');
        }

        $dateFrom      = $request->input('date_from', now()->subDays(30)->format('Y-m-d')) ?: null;
        $dateTo        = $request->input('date_to',   now()->format('Y-m-d'))              ?: null;
        $productFilter = $request->input('product_filter');
        $products      = $this->getProducts($shop->id);
        $datePreset    = $this->detectDatePreset($dateFrom, $dateTo);

        $cacheKey = "dashboard_{$shop->id}_{$this->shopCacheBust($shop->id)}_{$dateFrom}_{$dateTo}_{$productFilter}";
        $cached = Cache::remember($cacheKey, 1800, function () use ($shop, $dateFrom, $dateTo, $productFilter) {
            return [
                'kpis'             => $this->getKpis($shop->id, $dateFrom, $dateTo, $productFilter),
                'revenueChart'     => $this->getRevenueChart($shop->id, $dateFrom, $dateTo, $productFilter),
                'orderStatusChart' => $this->getOrderStatusChart($shop->id, $dateFrom, $dateTo, $productFilter),
                'topProvinces'     => $this->getTopProvinces($shop->id, $dateFrom, $dateTo, $productFilter),
                'topCouriers'      => $this->getTopCouriers($shop->id, $dateFrom, $dateTo, $productFilter),
            ];
        });

        $kpis             = $cached['kpis'];
        $revenueChart     = $cached['revenueChart'];
        $orderStatusChart = $cached['orderStatusChart'];
        $topProvinces     = $cached['topProvinces'];
        $topCouriers      = $cached['topCouriers'];

        if ($request->wantsJson()) {
            return response()->json([
                'kpis'             => $kpis,
                'revenueChart'     => $revenueChart,
                'orderStatusChart' => $orderStatusChart,
                'topProvinces'     => $topProvinces,
                'topCouriers'      => $topCouriers,
            ]);
        }

        return view('dashboard', compact(
            'shop', 'kpis', 'revenueChart', 'orderStatusChart',
            'topProvinces', 'topCouriers', 'dateFrom', 'dateTo',
            'products', 'productFilter', 'datePreset'
        ));
    }

    private function getKpis(int $shopId, ?string $from, ?string $to, ?string $pf = null): array
    {
        $q = Order::where('shop_id', $shopId)
            ->select(
                DB::raw('COUNT(*) as total_orders'),
                DB::raw("SUM(CASE WHEN status='delivered' THEN total_price ELSE 0 END) as total_revenue"),
                DB::raw("SUM(CASE WHEN status='delivered' THEN 1 ELSE 0 END) as delivered"),
                DB::raw('SUM(CASE WHEN is_rts=1 THEN 1 ELSE 0 END) as rts_count')
            );
        $this->applyDateFilter($q, $from, $to);
        $this->applyProductFilter($q, $pf);
        $row = $q->first();

        $revenueChange = 0;
        $ordersChange  = 0;
        if ($from && $to) {
            $prevFrom = Carbon::parse($from)->subDays(Carbon::parse($from)->diffInDays($to) + 1)->format('Y-m-d');
            $prevTo   = Carbon::parse($from)->subDay()->format('Y-m-d');
            $pq = Order::where('shop_id', $shopId)
                ->whereBetween('ordered_at', [$prevFrom . ' 00:00:00', $prevTo . ' 23:59:59'])
                ->select(
                    DB::raw('COUNT(*) as total_orders'),
                    DB::raw("SUM(CASE WHEN status='delivered' THEN total_price ELSE 0 END) as total_revenue")
                );
            $this->applyProductFilter($pq, $pf);
            $prevRow       = $pq->first();
            $revenueChange = $this->percentChange((float)$prevRow->total_revenue, (float)$row->total_revenue);
            $ordersChange  = $this->percentChange((int)$prevRow->total_orders,    (int)$row->total_orders);
        }

        // New customers: those whose first-ever order falls in the selected period.
        // Pre-aggregate MIN(ordered_at) per customer in one pass (uses orders_shop_customer_date
        // index), then filter with HAVING — avoids the per-row correlated NOT EXISTS.
        $newCustomers = 0;
        if ($from && $to) {
            $result = DB::selectOne("
                SELECT COUNT(*) as cnt
                FROM (
                    SELECT customer_pancake_id, MIN(ordered_at) AS first_ordered_at
                    FROM orders
                    WHERE shop_id = ?
                      AND customer_pancake_id IS NOT NULL
                    GROUP BY customer_pancake_id
                    HAVING first_ordered_at BETWEEN ? AND ?
                ) t
            ", [$shopId, $from . ' 00:00:00', $to . ' 23:59:59']);
            $newCustomers = (int) ($result->cnt ?? 0);
        }

        $customers    = Customer::where('shop_id', $shopId)->count();
        $totalOrders  = (int)   $row->total_orders;
        $totalRevenue = (float) $row->total_revenue;
        $delivered    = (int)   $row->delivered;
        $rts          = (int)   $row->rts_count;

        return [
            'total_revenue'       => $totalRevenue,
            'revenue_change'      => $revenueChange,
            'total_orders'        => $totalOrders,
            'orders_change'       => $ordersChange,
            'delivered'           => $delivered,
            'rts_count'           => $rts,
            'rts_rate'            => $totalOrders > 0 ? round(($rts / $totalOrders) * 100, 1) : 0,
            'total_customers'     => $customers,
            'new_customers'       => $newCustomers,
            'avg_order_value'     => $delivered > 0 ? round($totalRevenue / $delivered) : 0,
            'total_bottles_sold'  => $this->sumBottles($shopId, $from, $to, $pf, ["o.status = 'delivered'"]),
        ];
    }

    private function getRevenueChart(int $shopId, ?string $from, ?string $to, ?string $pf = null): array
    {
        $q = Order::where('shop_id', $shopId)
            ->select(
                DB::raw('DATE(ordered_at) as date'),
                DB::raw("SUM(CASE WHEN status='delivered' THEN total_price ELSE 0 END) as revenue"),
                DB::raw("SUM(CASE WHEN status='delivered' THEN 1 ELSE 0 END) as orders"),
                DB::raw('COUNT(*) as placed')
            )
            ->groupBy('date')->orderBy('date');
        $this->applyDateFilter($q, $from, $to);
        $this->applyProductFilter($q, $pf);
        $rows = $q->get()->keyBy('date');

        $allDates = $rows->keys()->sort()->values();

        return [
            'labels'  => $allDates->map(fn($d) => Carbon::parse($d)->format('M d'))->toArray(),
            'revenue' => $allDates->map(fn($d) => round($rows[$d]->revenue ?? 0))->toArray(),
            'orders'  => $allDates->map(fn($d) => (int) ($rows[$d]->orders ?? 0))->toArray(),
            'placed'  => $allDates->map(fn($d) => (int) ($rows[$d]->placed ?? 0))->toArray(),
        ];
    }

    private function getOrderStatusChart(int $shopId, ?string $from, ?string $to, ?string $pf = null): array
    {
        $q = Order::where('shop_id', $shopId)
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')->orderByDesc('count');
        $this->applyDateFilter($q, $from, $to);
        $this->applyProductFilter($q, $pf);
        $rows = $q->get();

        return [
            'labels' => $rows->pluck('status')->map(fn($s) => ucfirst(str_replace('_', ' ', $s)))->toArray(),
            'data'   => $rows->pluck('count')->toArray(),
        ];
    }

    private function getTopProvinces(int $shopId, ?string $from, ?string $to, ?string $pf = null): array
    {
        $q = Order::where('shop_id', $shopId)
            ->whereNotNull('province')
            ->select('province', DB::raw('COUNT(*) as orders'), DB::raw('SUM(total_price) as revenue'))
            ->groupBy('province')->orderByDesc('orders')->take(10);
        $this->applyDateFilter($q, $from, $to);
        $this->applyProductFilter($q, $pf);
        return $q->get()->toArray();
    }

    private function getTopCouriers(int $shopId, ?string $from, ?string $to, ?string $pf = null): array
    {
        $q = Order::where('shop_id', $shopId)
            ->whereNotNull('courier')
            ->select('courier', DB::raw('COUNT(*) as orders'), DB::raw('SUM(CASE WHEN is_rts=1 THEN 1 ELSE 0 END) as rts'))
            ->groupBy('courier')->orderByDesc('orders')->take(8);
        $this->applyDateFilter($q, $from, $to);
        $this->applyProductFilter($q, $pf);
        return $q->get()->toArray();
    }

    private function percentChange(float $old, float $new): float
    {
        if ($old == 0) return $new > 0 ? 100 : 0;
        return round((($new - $old) / $old) * 100, 1);
    }
}
