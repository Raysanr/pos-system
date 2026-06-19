<?php
namespace App\Http\Controllers;

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

        // Log the raw payload once so you can inspect the exact format Pancake sends
        Log::info('Pancake webhook received', ['payload' => $request->all()]);

        // Pancake sends the Pancake shop_id (numeric) as `shop_id` inside the payload.
        // `page_id` is the Facebook page ID — not what we store in PancakeShop::shop_id.
        $payload  = $request->all();
        $shopId   = (string) (
            $payload['shop_id']         ??  // real Pancake shop ID — primary key
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

        // Sync from 15 min ago to catch this change plus any near-concurrent ones
        $fromDate = now()->subMinutes(15)->format('Y-m-d H:i:s');
        dispatch(new SyncOrdersJob($shop->id, $fromDate));

        // Bust the shop cache token so all controllers generate fresh cache keys on next load
        $this->bustShopCache($shop->id);

        $this->startQueueWorker();

        return response()->json(['ok' => true]);
    }

    private function startQueueWorker(): void
    {
        $php     = escapeshellarg(PHP_BINARY);
        $artisan = escapeshellarg(base_path('artisan'));
        $log     = escapeshellarg(storage_path('logs/queue-worker.log'));
        shell_exec("nohup {$php} {$artisan} queue:work --stop-when-empty --timeout=3600 >> {$log} 2>&1 &");
    }
}
