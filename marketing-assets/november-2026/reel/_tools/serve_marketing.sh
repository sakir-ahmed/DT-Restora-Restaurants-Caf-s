#!/bin/bash
# Serves the app on http://localhost:8001 using the marketing database (your normal database.sqlite is not touched).
# A stale public/hot (Vite dev marker) is moved aside while this runs and restored on exit.
ROOT="$(cd "$(dirname "$0")/../../../.." && pwd)"
cd "$ROOT/web-app"
[ -f public/hot ] && mv public/hot public/hot.marketing-bak
# public/storage may point at another checkout (then every product photo 404s): point it here while serving, restore on exit
STORAGE_TARGET="$PWD/storage/app/public"
RESTORE_STORAGE=""
if [ -L public/storage ] && [ "$(readlink public/storage)" != "$STORAGE_TARGET" ]; then
  RESTORE_STORAGE="$(readlink public/storage)"
  rm public/storage && ln -s "$STORAGE_TARGET" public/storage
elif [ ! -e public/storage ]; then
  ln -s "$STORAGE_TARGET" public/storage; RESTORE_STORAGE="__remove__"
fi
cleanup() {
  [ -f public/hot.marketing-bak ] && mv public/hot.marketing-bak public/hot
  if [ "$RESTORE_STORAGE" = "__remove__" ]; then rm -f public/storage
  elif [ -n "$RESTORE_STORAGE" ]; then rm -f public/storage && ln -s "$RESTORE_STORAGE" public/storage; fi
}
trap cleanup EXIT
export DB_CONNECTION=sqlite DB_DATABASE="$PWD/database/marketing.sqlite" IS_DEMO=true SMS_DEMO=true APP_URL=http://localhost:8001 SESSION_DRIVER=file
export PHP_CLI_SERVER_WORKERS=8
INI="$(mktemp -d)"; printf "memory_limit=2G\nmax_execution_time=120\n" > "$INI/marketing.ini"; export PHP_INI_SCAN_DIR=":$INI"
php artisan serve --port=8001
