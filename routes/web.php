<?php

use App\Http\Controllers\AnalyticsPulseController;
use App\Http\Controllers\CustomerAnalyticsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MapAnalyticsController;
use App\Http\Controllers\ProductAudienceController;
use App\Http\Controllers\RtsAnalyticsController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn() => redirect()->route('dashboard'));

// Pancake webhook — no auth/CSRF (Pancake signs requests via IP allowlist on their end)
Route::post('/webhook/pancake', [WebhookController::class, 'handle'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
    ->name('webhook.pancake');

Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

Route::prefix('analytics')->name('analytics.')->group(function () {
    Route::get('/customers',        [CustomerAnalyticsController::class,  'index'])->name('customers');
    Route::get('/product-audience', [ProductAudienceController::class,   'index'])->name('product-audience');
    Route::get('/rts',              [RtsAnalyticsController::class,       'index'])->name('rts');
    Route::get('/map',              [MapAnalyticsController::class,       'index'])->name('map');
    Route::get('/map/data',         [MapAnalyticsController::class,       'index'])->name('map.data');
    Route::get('/pulse',            [AnalyticsPulseController::class,     'pulse'])->name('pulse');
});

Route::prefix('settings')->name('settings.')->group(function () {
    Route::get('/',         [SettingsController::class, 'index'])->name('index');
    Route::post('/',        [SettingsController::class, 'store'])->name('store');
    Route::post('/sync',    [SettingsController::class, 'sync'])->name('sync');
    Route::post('/detect',  [SettingsController::class, 'detect'])->name('detect');
});
