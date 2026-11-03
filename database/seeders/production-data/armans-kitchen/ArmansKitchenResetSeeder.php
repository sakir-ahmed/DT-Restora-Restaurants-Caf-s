<?php

declare(strict_types=1);

namespace Database\Seeders\ProductionData\ArmansKitchen;

use Database\Seeders\UnitSeeder;
use Illuminate\Database\Seeder;

/**
 * Resets the menu data only: units (every ingredient needs one), then the
 * branch, menu, ingredients and recipes in ArmansKitchenMenuSeeder. Users, roles,
 * settings and orders are not touched. Run it through reset-menu-data.sh.
 */
class ArmansKitchenResetSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UnitSeeder::class,
            ArmansKitchenMenuSeeder::class,
        ]);
    }
}
