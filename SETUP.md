# Pancake POS Analytics — Setup Guide

## Requirements
- PHP 8.2+
- MySQL 8.0+
- Composer 2.x
- Node 18+

## Quick Start

```bash
cd "pancake-analytics"

# 1. Copy env and generate key
cp .env.example .env
php artisan key:generate

# 2. Set DB credentials in .env
DB_DATABASE=pancake_analytics
DB_USERNAME=root
DB_PASSWORD=your_password

# 3. Run migrations
php artisan migrate

# 4. Start queue worker (terminal 1)
php artisan queue:work --timeout=3600

# 5. Start scheduler (add to crontab)
* * * * * cd /path/to/pancake-analytics && php artisan schedule:run >> /dev/null 2>&1

# 6. Start dev server (terminal 2)
php artisan serve

# 7. Build frontend
npm install && npm run dev
```

## How to Use
1. Register/Login at `http://localhost:8000`
2. Go to **Settings** → enter your Pancake POS **Shop ID** and **API Key**
3. Click **Test Connection** to verify
4. Click **Save & Connect**
5. Click **Sync Now** to fetch all data (runs in background queue)
6. View analytics at Dashboard, Customer Analytics, RTS & Returns, PH Map

## API Credentials
- **Base URL**: `https://pos.pages.fm/api/v1`
- **Rate Limit**: 1,000 req/min · 10,000 req/hour
- Get API Key: Pancake POS → Settings → App Settings → API Key → Create

## Architecture
- `app/Services/PancakeApiService.php` — API client with pagination
- `app/Jobs/SyncCustomersJob.php` — Queued customer sync
- `app/Jobs/SyncOrdersJob.php` — Queued orders sync
- `app/Http/Controllers/DashboardController.php` — Main KPIs
- `app/Http/Controllers/CustomerAnalyticsController.php` — Demographics
- `app/Http/Controllers/RtsAnalyticsController.php` — RTS analysis
- `app/Http/Controllers/MapAnalyticsController.php` — Province map data
- `routes/console.php` — Auto-sync scheduler
