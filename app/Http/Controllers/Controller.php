<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

abstract class Controller
{
    protected function user(): User
    {
        /** @var User */
        return Auth::user();
    }

    protected function getProducts(int $shopId): array
    {
        return Cache::remember("products_{$shopId}", 3600, function () use ($shopId) {
            // Group by lowercase to deduplicate variants like "GINSENG SERUM" / "Ginseng Serum".
            // Within each group, pick the variant with the most orders (highest count comes first).
            $rows = DB::select("
                SELECT json_extract(item.value, '$.variation_info.name') as name,
                       COUNT(*) as cnt,
                       LOWER(json_extract(item.value, '$.variation_info.name')) as name_lower
                FROM orders, json_each(orders.items) as item
                WHERE orders.shop_id = ?
                  AND json_extract(item.value, '$.variation_info.name') IS NOT NULL
                GROUP BY name_lower, name
                ORDER BY name_lower, cnt DESC
            ", [$shopId]);

            $seen   = [];
            $result = [];
            foreach ($rows as $row) {
                $key = $row->name_lower;
                if (!isset($seen[$key])) {
                    $seen[$key] = true;
                    $result[]   = $row->name; // first = highest count for this lowercase group
                }
            }
            usort($result, 'strcasecmp');
            return $result;
        });
    }

    protected function applyProductFilter($query, ?string $productFilter): mixed
    {
        if ($productFilter) {
            // COLLATE NOCASE makes "Clear Sight 3.0" match "Clear sight 3.0" etc.
            $query->whereRaw(
                "EXISTS (SELECT 1 FROM json_each(items) as item WHERE json_extract(item.value, '$.variation_info.name') = ? COLLATE NOCASE)",
                [$productFilter]
            );
        }
        return $query;
    }

    protected function applyDateFilter($query, ?string $from, ?string $to): mixed
    {
        if ($from && $to) {
            $query->whereBetween('ordered_at', [$from . ' 00:00:00', $to . ' 23:59:59']);
        }
        return $query;
    }

    protected function detectDatePreset(?string $from, ?string $to): string
    {
        if (!$from && !$to) return 'all';
        $today = now()->format('Y-m-d');
        if ($from === now()->subDays(7)->format('Y-m-d')  && $to === $today) return '7d';
        if ($from === now()->subDays(30)->format('Y-m-d') && $to === $today) return '30d';
        if ($from === now()->subDays(90)->format('Y-m-d') && $to === $today) return '90d';
        return 'custom';
    }

    protected function shopCacheBust(int $shopId): string
    {
        return (string) Cache::get("shop_bust_{$shopId}", 0);
    }

    protected function bustShopCache(int $shopId): void
    {
        Cache::put("shop_bust_{$shopId}", time(), now()->addDays(30));
    }

    // Sum total bottles across orders by parsing the leading integer from the variation name
    // (e.g. "4 Clear Sight 3.0" → 4 bottles) multiplied by the item's own quantity field.
    // $extraClauses / $extraParams let callers add conditions like "o.status = 'delivered'".
    protected function sumBottles(int $shopId, ?string $from, ?string $to, ?string $pf = null, array $extraClauses = [], array $extraParams = []): int
    {
        $whereParts = ['o.shop_id = ?'];
        $params     = [$shopId];

        if ($from && $to) {
            $whereParts[] = 'o.ordered_at BETWEEN ? AND ?';
            $params[]     = $from . ' 00:00:00';
            $params[]     = $to   . ' 23:59:59';
        }
        if ($pf) {
            $whereParts[] = "EXISTS (SELECT 1 FROM json_each(o.items) AS pf_check WHERE json_extract(pf_check.value, '$.variation_info.name') = ? COLLATE NOCASE)";
            $params[]     = $pf;
        }
        foreach ($extraClauses as $clause) {
            $whereParts[] = $clause;
        }
        $params = array_merge($params, $extraParams);
        $where  = implode(' AND ', $whereParts);

        $result = DB::selectOne("
            SELECT SUM(
                MAX(CAST(json_extract(item.value, '$.variation_info.name') AS INTEGER), 1)
                * MAX(CAST(COALESCE(json_extract(item.value, '$.quantity'), 1) AS INTEGER), 1)
            ) as total_bottles
            FROM orders o, json_each(o.items) AS item
            WHERE {$where}
        ", $params);

        return (int) ($result?->total_bottles ?? 0);
    }
}
