<?php

namespace App\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // After each SQLite auto-checkpoint, truncate the WAL file to 0 bytes.
        // Without this, SQLite's default PASSIVE checkpoint leaves the WAL growing
        // indefinitely — measured at 44.9 MB, adding ~7x overhead to every query.
        DB::statement('PRAGMA journal_size_limit = 0');
    }
}
