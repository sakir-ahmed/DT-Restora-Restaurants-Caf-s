<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * Gives menu items, variants and add-ons a numeric code that is unique across
 * all three tables, so a cashier can key it in at the POS.
 */
trait HasMenuNumber
{
    public static function bootHasMenuNumber(): void
    {
        static::creating(static function ($model): void {
            if ($model->menu_no === null) {
                $model->menu_no = self::nextMenuNumber();
            }
        });
    }

    public static function nextMenuNumber(): int
    {
        $highest = max(
            (int) DB::table('menu_items')->max('menu_no'),
            (int) DB::table('menu_item_variants')->max('menu_no'),
            (int) DB::table('menu_item_addons')->max('menu_no'),
        );

        return $highest + 1;
    }
}
