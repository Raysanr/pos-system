<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ReturnReasonController extends Controller
{
    public function index(Request $request)
    {
        $shop          = $this->user()->shops()->where('is_active', true)->firstOrFail();
        $dateFrom      = $request->input('date_from') ?: null;
        $dateTo        = $request->input('date_to')   ?: null;
        $productFilter = $request->input('product_filter');
        $products      = $this->getProducts($shop->id);
        $datePreset    = $this->detectDatePreset($dateFrom, $dateTo);

        $cacheKey = "return_reasons_{$shop->id}_{$this->shopCacheBust($shop->id)}_{$dateFrom}_{$dateTo}_{$productFilter}";
        $data = Cache::remember($cacheKey, 1800, function () use ($shop, $dateFrom, $dateTo, $productFilter) {
            return [
                'overview'   => $this->getReasonOverview($shop->id, $dateFrom, $dateTo, $productFilter),
                'byProduct'  => $this->getReasonByProduct($shop->id, $dateFrom, $dateTo, $productFilter),
                'byProvince' => $this->getReasonByProvince($shop->id, $dateFrom, $dateTo, $productFilter),
            ];
        });

        $overview   = $data['overview'];
        $byProduct  = $data['byProduct'];
        $byProvince = $data['byProvince'];

        $totalReturned = array_sum(array_column($overview, 'count'));
        $topReason     = $overview[0] ?? null;
        $uniqueReasons = count($overview);

        return view('analytics.return-reasons', compact(
            'shop', 'overview', 'byProduct', 'byProvince',
            'totalReturned', 'topReason', 'uniqueReasons',
            'dateFrom', 'dateTo', 'datePreset', 'products', 'productFilter'
        ));
    }

    private function getReasonOverview(int $shopId, ?string $from, ?string $to, ?string $pf): array
    {
        $dateClause = $this->dateClause($from, $to);
        $pfClause   = $pf ? "AND LOWER(json_extract(items, '$[0].variation_info.name')) = LOWER(?)" : '';

        $sql = "
            SELECT return_reason AS reason,
                   COUNT(*) AS count,
                   ROUND(COUNT(*) * 100.0 / (
                       SELECT COUNT(*) FROM orders
                       WHERE shop_id = ? AND is_returned = 1
                         AND return_reason IS NOT NULL AND return_reason != ''
                         {$dateClause} {$pfClause}
                   ), 1) AS pct
            FROM orders
            WHERE shop_id = ?
              AND is_returned = 1
              AND return_reason IS NOT NULL
              AND return_reason != ''
              {$dateClause} {$pfClause}
            GROUP BY return_reason
            ORDER BY count DESC
            LIMIT 20
        ";

        $params = [$shopId];
        if ($from) $params[] = $from;
        if ($to)   $params[] = $to;
        if ($pf)   $params[] = $pf;
        $params[] = $shopId;
        if ($from) $params[] = $from;
        if ($to)   $params[] = $to;
        if ($pf)   $params[] = $pf;

        $rows = DB::select($sql, $params);
        return array_map(fn($r) => [
            'reason' => $r->reason,
            'count'  => (int)   $r->count,
            'pct'    => (float) $r->pct,
        ], $rows);
    }

    private function getReasonByProduct(int $shopId, ?string $from, ?string $to, ?string $pf): array
    {
        $dateClause = $this->dateClause($from, $to);
        $pfClause   = $pf ? "AND LOWER(json_extract(item.value, '$.variation_info.name')) = LOWER(?)" : '';

        $sql = "
            WITH returned_orders AS (
                SELECT o.return_reason,
                       MIN(LOWER(json_extract(item.value, '$.variation_info.name'))) AS product
                FROM orders o
                JOIN json_each(o.items) AS item
                WHERE o.shop_id = ?
                  AND o.is_returned = 1
                  AND o.return_reason IS NOT NULL
                  AND o.return_reason != ''
                  AND json_extract(item.value, '$.variation_info.name') IS NOT NULL
                  {$dateClause} {$pfClause}
                GROUP BY o.id
            )
            SELECT product,
                   return_reason AS reason,
                   COUNT(*) AS count
            FROM returned_orders
            WHERE product IS NOT NULL
            GROUP BY product, reason
            ORDER BY count DESC
        ";

        $params = [$shopId];
        if ($from) $params[] = $from;
        if ($to)   $params[] = $to;
        if ($pf)   $params[] = $pf;

        $rows = DB::select($sql, $params);
        return array_map(fn($r) => [
            'product' => $r->product,
            'reason'  => $r->reason,
            'count'   => (int) $r->count,
        ], $rows);
    }

    private function getReasonByProvince(int $shopId, ?string $from, ?string $to, ?string $pf): array
    {
        $dateClause = $this->dateClause($from, $to);
        $pfClause   = $pf ? "AND LOWER(json_extract(items, '$[0].variation_info.name')) = LOWER(?)" : '';

        $sql = "
            SELECT province,
                   return_reason AS reason,
                   COUNT(*) AS count
            FROM orders
            WHERE shop_id = ?
              AND is_returned = 1
              AND return_reason IS NOT NULL
              AND return_reason != ''
              AND province IS NOT NULL
              AND province != ''
              {$dateClause} {$pfClause}
            GROUP BY province, reason
            ORDER BY count DESC
        ";

        $params = [$shopId];
        if ($from) $params[] = $from;
        if ($to)   $params[] = $to;
        if ($pf)   $params[] = $pf;

        $rows = DB::select($sql, $params);
        return array_map(fn($r) => [
            'province' => $r->province,
            'reason'   => $r->reason,
            'count'    => (int) $r->count,
        ], $rows);
    }

    private function dateClause(?string $from, ?string $to): string
    {
        $parts = [];
        if ($from) $parts[] = "AND ordered_at >= ?";
        if ($to)   $parts[] = "AND ordered_at < DATE(?, '+1 day')";
        return implode(' ', $parts);
    }
}
