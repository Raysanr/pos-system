<?php
use App\Jobs\SyncCustomersJob;
use App\Jobs\SyncOrdersJob;
use App\Models\PancakeShop;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function () {
    PancakeShop::where('is_active', true)->each(function ($shop) {
        $hoursAgo = now()->subHours($shop->sync_interval_hours);
        if (!$shop->last_synced_at || $shop->last_synced_at->lt($hoursAgo)) {
            SyncCustomersJob::dispatch($shop->id);
            SyncOrdersJob::dispatch($shop->id);
        }
    });
})->hourly()->name('pancake-auto-sync')->withoutOverlapping();
