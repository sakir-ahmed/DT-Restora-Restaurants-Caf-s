#!/usr/bin/env bash
#
# Reset the menu data only and load the costed menu (see ArmansKitchenMenuSeeder):
# units, branch, menu items (no variants), ingredients and recipes.
#
# This DELETES the current menu, ingredients, recipes, purchases, stock records,
# wastage, stock counts and transfers. Users, roles, settings, orders and
# accounts are kept. It stops without changes if any order uses a menu item.
#
# Usage:
#   ./reset-menu-data.sh        ask for confirmation, then reset
#   ./reset-menu-data.sh -y     do not ask

set -Eeuo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")"

if [ "${1:-}" != "-y" ] && [ "${1:-}" != "--yes" ]; then
    printf 'This will replace the current menu, ingredients, recipes and stock records. Type "yes" to continue: '
    read -r answer
    [ "$answer" = "yes" ] || { echo "Cancelled."; exit 1; }
fi

php artisan migrate --force
php artisan db:seed --class='Database\Seeders\ProductionData\ArmansKitchen\ArmansKitchenResetSeeder' --force
php artisan optimize:clear
