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

# Product photos as PNG: the app ships them as webp (marketing-assets and exports want png). Convert once into the
# git-ignored storage folder, then point every menu item and media row at the png.
DEMO_DIR="$PWD/storage/app/public/demo"
for f in "$DEMO_DIR"/*.webp; do
  png="${f%.webp}.png"
  [ -f "$png" ] || ffmpeg -y -loglevel error -i "$f" "$png"
done
sqlite3 "$DB_DATABASE" "update menu_items set photo_path = replace(photo_path, '.webp', '.png') where photo_path like 'demo/%.webp'; update media set path = replace(path, '.webp', '.png') where path like 'demo/%.webp';"
echo "product photos now png"
