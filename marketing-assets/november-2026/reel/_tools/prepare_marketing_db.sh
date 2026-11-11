#!/bin/bash
# Builds web-app/database/marketing.sqlite: a fresh demo database (IS_DEMO) plus the showcase top-up.
# Your normal database.sqlite is never touched. Takes about a minute.
set -e
ROOT="$(cd "$(dirname "$0")/../../../.." && pwd)"
cd "$ROOT/web-app"
export DB_CONNECTION=sqlite DB_DATABASE="$PWD/database/marketing.sqlite" IS_DEMO=true
touch "$DB_DATABASE"
php artisan migrate:fresh --seed --force > /dev/null
php "$ROOT/marketing-assets/november-2026/reel/_tools/run_showcase.php"
