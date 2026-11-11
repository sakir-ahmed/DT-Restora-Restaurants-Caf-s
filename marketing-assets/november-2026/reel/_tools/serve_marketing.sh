#!/bin/bash
# Serves the app on http://localhost:8001 using the marketing database (your normal database.sqlite is not touched).
# A stale public/hot (Vite dev marker) is moved aside while this runs and restored on exit.
ROOT="$(cd "$(dirname "$0")/../../../.." && pwd)"
cd "$ROOT/web-app"
[ -f public/hot ] && mv public/hot public/hot.marketing-bak
trap '[ -f public/hot.marketing-bak ] && mv public/hot.marketing-bak public/hot' EXIT
export DB_CONNECTION=sqlite DB_DATABASE="$PWD/database/marketing.sqlite" IS_DEMO=true SMS_DEMO=true APP_URL=http://localhost:8001 SESSION_DRIVER=file
export PHP_CLI_SERVER_WORKERS=8
INI="$(mktemp -d)"; printf "memory_limit=2G\nmax_execution_time=120\n" > "$INI/marketing.ini"; export PHP_INI_SCAN_DIR=":$INI"
php artisan serve --port=8001
