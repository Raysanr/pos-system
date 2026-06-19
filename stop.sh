#!/bin/bash

echo ""
echo "  Stopping SH Customer's Analytics..."

pkill -f "artisan serve"  2>/dev/null && echo "  ✓  Laravel server stopped"
pkill -f "artisan queue"  2>/dev/null && echo "  ✓  Queue worker stopped"
pkill -f "ngrok http"     2>/dev/null && echo "  ✓  ngrok stopped"

echo "  Done."
echo ""
