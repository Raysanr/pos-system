<?php
use App\Jobs\ReconcileOrdersJob;
use App\Jobs\RefreshOpenOrdersJob;
use App\Jobs\SyncCustomersJob;
use App\Jobs\SyncOrdersJob;
use App\Models\PancakeShop;
use Illuminate\Support\Facades\Schedule;

// ── Hourly full sync ───────────────────────────────────────────────────────────
// Resumes from its cursor page; sets last_synced_at when all pages are done.
Schedule::call(function () {
    PancakeShop::where('is_active', true)->each(function ($shop) {
        $hoursAgo = now()->subHours($shop->sync_interval_hours);
        if (!$shop->last_synced_at || $shop->last_synced_at->lt($hoursAgo)) {
            SyncCustomersJob::dispatch($shop->id);
            SyncOrdersJob::dispatch($shop->id);
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

// ── Daily 3 am: open-order sweep ───────────────────────────────────────────────
// Re-fetches every non-terminal order individually to catch status changes on
// older orders that the 5-min reconciliation (newest pages only) doesn't reach.
Schedule::call(function () {
    PancakeShop::where('is_active', true)->each(function ($shop) {
        RefreshOpenOrdersJob::dispatch($shop->id);
    });
})->dailyAt('03:00')->name('refresh-open-orders')->withoutOverlapping();
