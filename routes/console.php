<?php
use App\Jobs\ReconcileOrdersJob;
use App\Jobs\RefreshOpenOrdersJob;
use App\Jobs\SyncCustomersJob;
use App\Jobs\SyncOrdersJob;
use App\Models\PancakeShop;
use Illuminate\Support\Facades\Schedule;

// ── Hourly rolling sync ────────────────────────────────────────────────────────
// Orders: only the last 30 days (228 pages vs 2,244 total). Status changes on
// older orders are already handled by RefreshOpenOrdersJob every 2 hours.
// Customers: paginated in 200-page chunks; job handles per-page failures itself.
Schedule::call(function () {
    PancakeShop::where('is_active', true)->each(function ($shop) {
        $hoursAgo = now()->subHours($shop->sync_interval_hours);
        if (!$shop->last_synced_at || $shop->last_synced_at->lt($hoursAgo)) {
            SyncCustomersJob::dispatch($shop->id);
            SyncOrdersJob::dispatch($shop->id, now()->subDays(30)->format('Y-m-d H:i:s'));
        }
    });
})->hourly()->name('pancake-full-sync')->withoutOverlapping();

// ── Every 5 minutes: lightweight reconciliation ────────────────────────────────
// Fetches the 3 newest pages in parallel — catches new orders and recent status
// changes (delivered, returned, etc.) without waiting for the hourly cycle.
Schedule::call(function () {
    PancakeShop::where('is_active', true)->each(function ($shop) {
        ReconcileOrdersJob::dispatch($shop->id);
    });
})->everyFiveMinutes()->name('reconcile-orders')->withoutOverlapping();

// ── Every 2 hours: open-order sweep ───────────────────────────────────────────
// Re-fetches every non-terminal order individually to catch status changes on
// older orders that the 5-min reconciliation (newest pages only) doesn't reach.
// Running every 2 hours instead of daily so deliveries show up same-day.
Schedule::call(function () {
    PancakeShop::where('is_active', true)->each(function ($shop) {
        RefreshOpenOrdersJob::dispatch($shop->id);
    });
})->everyTwoHours()->name('refresh-open-orders')->withoutOverlapping();
