#!/usr/bin/env sh
set -e
cd /app

# Ensure the SQLite database file exists on the mounted Railway volume
mkdir -p database
[ -f database/database.sqlite ] || touch database/database.sqlite

# Cache config/routes/views for performance
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Create/update tables (safe to run every boot)
php artisan migrate --force

# Start the queue worker in the background — this is what runs "Sync Now"
php artisan queue:work --sleep=3 --tries=3 --timeout=3600 &

# Start the web server bound to the port Railway provides
exec php artisan serve --host 0.0.0.0 --port "${PORT:-8000}"
