<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            ChartOfAccountsSeeder::class,
            BranchSeeder::class,
            UnitSeeder::class,
            RootAdminSeeder::class,
            DemoStaffSeeder::class,
            DemoMenuSeeder::class,
            DemoInventorySeeder::class,
            DemoSalesSeeder::class,
        ]);
    }
}
