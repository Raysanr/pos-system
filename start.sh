#!/bin/bash

# ─────────────────────────────────────────────
#  SH Customer's Analytics — One-command start
#  Usage: ./start.sh
# ─────────────────────────────────────────────

APP_DIR="/Users/junioraiengineer/POS SYSTEM/pancake-analytics"

# ── PUT YOUR NGROK STATIC DOMAIN HERE ────────
# Get it free at: dashboard.ngrok.com/domains
NGROK_DOMAIN="juniors-hub.ngrok-free.app"
# ─────────────────────────────────────────────

cd "$APP_DIR"

echo ""
echo "  ┌────────────────────────────────────┐"
echo "  │  SH Customer's Analytics           │"
echo "  │  Starting all services...          │"
echo "  └────────────────────────────────────┘"
echo ""

# Stop anything already running
pkill -f "artisan serve"    2>/dev/null
pkill -f "artisan queue"    2>/dev/null
pkill -f "ngrok http"       2>/dev/null
sleep 1

# 1. Laravel server
php artisan serve --port=8000 >> storage/logs/serve.log 2>&1 &
SERVE_PID=$!
echo "  ✓  Laravel server   → http://localhost:8000  (PID $SERVE_PID)"

sleep 1

# 2. Queue worker (processes background sync jobs)
php artisan queue:work --timeout=3600 --sleep=3 --tries=3 >> storage/logs/queue-worker.log 2>&1 &
QUEUE_PID=$!
echo "  ✓  Queue worker     → running                (PID $QUEUE_PID)"

sleep 1

# 3. ngrok tunnel
if [ "$NGROK_DOMAIN" = "YOUR-STATIC-DOMAIN.ngrok-free.app" ]; then
    echo ""
    echo "  ⚠  ngrok static domain not set."
    echo "     Get yours free at dashboard.ngrok.com/domains"
    echo "     Then edit NGROK_DOMAIN inside this file."
    echo ""
    ngrok http 8000 >> storage/logs/ngrok.log 2>&1 &
else
    ngrok http --domain="$NGROK_DOMAIN" 8000 >> storage/logs/ngrok.log 2>&1 &
    echo "  ✓  ngrok tunnel     → https://$NGROK_DOMAIN"
fi
NGROK_PID=$!

sleep 2

# 4. Open browser
open http://localhost:8000

echo ""
echo "  ─────────────────────────────────────"
echo "  All services running!"
echo "  Logs → storage/logs/"
echo ""
echo "  To stop everything: ./stop.sh"
echo "  ─────────────────────────────────────"
echo ""

# Keep script alive so Ctrl+C stops everything cleanly
trap "echo ''; echo '  Stopping all services...'; kill $SERVE_PID $QUEUE_PID $NGROK_PID 2>/dev/null; echo '  Done.'; exit" INT
wait
