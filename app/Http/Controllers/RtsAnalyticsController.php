<?php
namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RtsAnalyticsController extends Controller
{
    public function index(Request $request)
    {
        $shop     = $this->user()->shops()->where('is_active', true)->firstOrFail();
        $dateFrom      = $request->input('date_from', now()->subDays(30)->format('Y-m-d')) ?: null;
        $dateTo        = $request->input('date_to',   now()->format('Y-m-d'))              ?: null;
        $productFilter = $request->input('product_filter');
        $products      = $this->getProducts($shop->id);
        $datePreset    = $this->detectDatePreset($dateFrom, $dateTo);

        $cacheKey = "rts_{$shop->id}_{$this->shopCacheBust($shop->id)}_{$dateFrom}_{$dateTo}_{$productFilter}";
        $cached = Cache::remember($cacheKey, 1800, function () use ($shop, $dateFrom, $dateTo, $productFilter) {
            return [
                'rtsByProvince' => $this->getRtsByProvince($shop->id, $dateFrom, $dateTo, $productFilter),
                'rtsByCourier'  => $this->getRtsByCourier($shop->id, $dateFrom, $dateTo, $productFilter),
                'rtsTrend'      => $this->getRtsTrend($shop->id, $dateFrom, $dateTo, $productFilter),
                'returnReasons' => $this->getReturnReasons($shop->id, $dateFrom, $dateTo, $productFilter),
                'rtsKpis'       => $this->getRtsKpis($shop->id, $dateFrom, $dateTo, $productFilter),
            ];
        });

        $rtsByProvince = $cached['rtsByProvince'];
        $rtsByCourier  = $cached['rtsByCourier'];
        $rtsTrend      = $cached['rtsTrend'];
        $returnReasons = $cached['returnReasons'];
        $rtsKpis       = $cached['rtsKpis'];

        if ($request->wantsJson()) {
            return response()->json([
                'rtsKpis'       => $rtsKpis,
                'rtsTrend'      => $rtsTrend,
                'rtsByCourier'  => $rtsByCourier,
                'rtsByProvince' => $rtsByProvince,
                'returnReasons' => $returnReasons,
            ]);
        }

        return view('analytics.rts', compact(
            'shop', 'rtsByProvince', 'rtsByCourier', 'rtsTrend',
            'returnReasons', 'rtsKpis', 'dateFrom', 'dateTo',
            'products', 'productFilter', 'datePreset'
        ));
    }

    private function getRtsKpis(int $shopId, ?string $from, ?string $to, ?string $pf = null): array
    {
        $q = Order::where('shop_id', $shopId)->select(
            DB::raw('COUNT(*) as total_orders'),
            DB::raw('SUM(CASE WHEN is_rts=1 THEN 1 ELSE 0 END) as rts_count'),
            DB::raw('SUM(CASE WHEN is_returned=1 THEN 1 ELSE 0 END) as returned'),
            DB::raw('SUM(CASE WHEN is_cancelled=1 THEN 1 ELSE 0 END) as cancelled'),
            DB::raw('SUM(CASE WHEN is_rts=1 THEN total_price ELSE 0 END) as revenue_lost')
        );
        $this->applyDateFilter($q, $from, $to);
        $this->applyProductFilter($q, $pf);
        $row = $q->first();

        $total = (int) $row->total_orders;
        $rts   = (int) $row->rts_count;

        return [
            'total_orders'  => $total,
            'rts_count'     => $rts,
            'rts_rate'      => $total > 0 ? round(($rts / $total) * 100, 1) : 0,
            'returned'      => (int) $row->returned,
            'cancelled'     => (int) $row->cancelled,
            'revenue_lost'  => (float) $row->revenue_lost,
            'bottles_lost'  => $this->sumBottles($shopId, $from, $to, $pf, ['o.is_rts = 1']),
        ];
    }

    private function getRtsByProvince(int $shopId, ?string $from, ?string $to, ?string $pf = null): array
    {
        $q = Order::where('shop_id', $shopId)->whereNotNull('province')
            ->select('province',
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN is_rts=1 THEN 1 ELSE 0 END) as rts'),
                DB::raw('ROUND(SUM(CASE WHEN is_rts=1 THEN 1 ELSE 0 END)*100.0/COUNT(*),1) as rts_rate')
            )->groupBy('province')->orderByDesc('rts')->take(20);
        $this->applyDateFilter($q, $from, $to);
        $this->applyProductFilter($q, $pf);
        return $q->get()->toArray();
    }

    private function getRtsByCourier(int $shopId, ?string $from, ?string $to, ?string $pf = null): array
    {
        $q = Order::where('shop_id', $shopId)->whereNotNull('courier')
            ->select('courier',
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN is_rts=1 THEN 1 ELSE 0 END) as rts'),
                DB::raw('ROUND(SUM(CASE WHEN is_rts=1 THEN 1 ELSE 0 END)*100.0/COUNT(*),1) as rts_rate')
            )->groupBy('courier')->orderByDesc('rts');
        $this->applyDateFilter($q, $from, $to);
        $this->applyProductFilter($q, $pf);
        return $q->get()->toArray();
    }

    private function getRtsTrend(int $shopId, ?string $from, ?string $to, ?string $pf = null): array
    {
        $q = Order::where('shop_id', $shopId)
            ->select(DB::raw('DATE(ordered_at) as date'), DB::raw('COUNT(*) as total'), DB::raw('SUM(CASE WHEN is_rts=1 THEN 1 ELSE 0 END) as rts'))
            ->groupBy('date')->orderBy('date');
        $this->applyDateFilter($q, $from, $to);
        $this->applyProductFilter($q, $pf);
        $rows = $q->get();

        return [
            'labels' => $rows->pluck('date')->map(fn($d) => \Carbon\Carbon::parse($d)->format('M d'))->toArray(),
            'total'  => $rows->pluck('total')->toArray(),
            'rts'    => $rows->pluck('rts')->toArray(),
        ];
    }

    private function getReturnReasons(int $shopId, ?string $from, ?string $to, ?string $pf = null): array
    {
        $q = Order::where('shop_id', $shopId)->where('is_rts', true)->whereNotNull('return_reason')
            ->select('return_reason', DB::raw('COUNT(*) as count'))
            ->groupBy('return_reason')->orderByDesc('count')->take(10);
        $this->applyDateFilter($q, $from, $to);
        $this->applyProductFilter($q, $pf);
        return $q->get()->toArray();
    }
}
