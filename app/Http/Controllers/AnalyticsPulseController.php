<?php
namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

/**
 * Lightweight endpoint polled by the browser every 30 seconds.
 * Returns the shop's cache-bust timestamp so the client can detect
 * when a webhook or reconciliation job updated the data and trigger
 * a silent in-place refresh without the user having to do anything.
 */
class AnalyticsPulseController extends Controller
{
    public function pulse(): JsonResponse
    {
        $shop = $this->user()->shops()->where('is_active', true)->firstOrFail();

        return response()->json([
            'updated_at' => (int) $this->shopCacheBust($shop->id),
        ]);
    }
}
