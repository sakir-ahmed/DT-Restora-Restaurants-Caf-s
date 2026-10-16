#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/../.."

db="$(pwd)/database/e2e.sqlite"
hot_backup=""

cleanup() {
    if [[ -n "${child:-}" ]]; then
        kill "$child" 2>/dev/null || true
    fi

    if [[ -n "$hot_backup" && -f "$hot_backup" ]]; then
        mv "$hot_backup" public/hot
    fi
}

trap cleanup EXIT INT TERM

if [[ -f public/hot ]]; then
    hot_backup="$(pwd)/public/hot.e2e-bak"
    mv public/hot "$hot_backup"
fi

export APP_URL="http://127.0.0.1:8099"
export DB_CONNECTION="sqlite"
export DB_DATABASE="$db"
export DB_URL="sqlite:///${db}"
export SESSION_DRIVER="file"
export CACHE_STORE="file"
export QUEUE_CONNECTION="sync"
export BROADCAST_CONNECTION="null"
export IS_DEMO="false"
export SMS_DEMO="false"

npx vite build
rm -f "$db"
php artisan migrate:fresh --force --seeder=E2ePosSeeder --no-interaction
php artisan serve --host=127.0.0.1 --port=8099 &
child=$!
wait "$child"
