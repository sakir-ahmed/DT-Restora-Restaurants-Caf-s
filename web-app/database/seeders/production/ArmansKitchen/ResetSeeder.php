<?php

declare(strict_types=1);

namespace Database\Seeders\Production\ArmansKitchen;

use Database\Seeders\UnitSeeder;
use Illuminate\Database\Seeder;

/**
 * Resets the menu data only: units (every ingredient needs one), then the
 * branch, menu, ingredients and recipes in MenuSeeder. Users, roles,
 * settings and orders are not touched. Run it with db:seed.
 */
class ResetSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UnitSeeder::class,
            MenuSeeder::class,
        ]);
    }
}
