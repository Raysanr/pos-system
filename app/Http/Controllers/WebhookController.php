<?php
namespace App\Http\Controllers;

use App\Jobs\RefreshOpenOrdersJob;
use App\Jobs\SyncOrdersJob;
use App\Models\PancakeShop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        // Verify secret header if configured
        $secret = config('services.pancake.webhook_secret');
        if ($secret && $request->header('X-Webhook-Secret') !== $secret) {
            Log::warning('Pancake webhook: invalid secret');
            return response()->json(['ok' => false], 401);
        }

        $payload = $request->all();

        // Log once so we can verify the exact format Pancake sends.
        Log::info('Pancake webhook received', ['payload' => $payload]);

        // Pancake sends the numeric shop ID as `shop_id` inside the payload.
        $shopId = (string) (
            $payload['shop_id']         ??
            $payload['data']['shop_id'] ??
            ''
        );

        if (!$shopId) {
            Log::warning('Pancake webhook: missing shop_id', ['payload_keys' => array_keys($payload)]);
            return response()->json(['ok' => true]);
        }

        $shop = PancakeShop::where('shop_id', $shopId)->where('is_active', true)->first();

        if (!$shop) {
            Log::warning('Pancake webhook: unknown shop', ['shop_id' => $shopId]);
            return response()->json(['ok' => true]);
        }

        // Extract order IDs from whatever structure Pancake uses.
        // Try the most common locations; add more if the log reveals a different shape.
        $orderIds = array_values(array_unique(array_filter([
            $payload['order_id']        ?? null,
            $payload['id']              ?? null,
            $payload['data']['id']      ?? null,
            $payload['data']['order_id'] ?? null,
        ], fn($v) => $v !== null)));

        if (!empty($orderIds)) {
            // Targeted refresh: re-fetch only the order(s) Pancake told us changed.
            // This updates the status even for orders created months ago.
            dispatch(new RefreshOpenOrdersJob($shop->id, $orderIds));
        } else {
            // Payload didn't contain a recognisable order ID — fall back to a
            // short window sync to catch new/recently-created orders.
            $fromDate = now()->subMinutes(15)->format('Y-m-d H:i:s');
            dispatch(new SyncOrdersJob($shop->id, $fromDate));
        }

        $this->bustShopCache($shop->id);
        $this->startQueueWorker();

        return response()->json(['ok' => true]);
    }

    private function startQueueWorker(): void
    {
        // Don't spawn a second worker when one is already running.
        // Without this check, every webhook spawns a new process — during peak
        // delivery hours this accumulates dozens of concurrent writers and causes
        // SQLite WAL bloat (measured at 44.9 MB, adding ~7x query overhead).
        $running = trim((string) shell_exec("pgrep -f 'artisan queue:work' 2>/dev/null"));
        if (!empty($running)) {
            return;
        }

        $php     = escapeshellarg(PHP_BINARY);
        $artisan = escapeshellarg(base_path('artisan'));
        $log     = escapeshellarg(storage_path('logs/queue-worker.log'));
        shell_exec("nohup {$php} {$artisan} queue:work --stop-when-empty --timeout=3600 >> {$log} 2>&1 &");
    }
}
