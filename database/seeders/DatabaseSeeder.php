<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Application bootstrap data always seeds. Branches, menu, staff and the
     * rest of the sample data seed only when IS_DEMO=true.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            ChartOfAccountsSeeder::class,
            UnitSeeder::class,
            PaymentMethodSeeder::class,
            SettingSeeder::class,
            RootAdminSeeder::class,
        ]);

        if (config('app.is_demo') !== true) {
            return;
        }

        $this->call([
            BranchSeeder::class,
            DemoStaffSeeder::class,
            DemoMenuSeeder::class,
            DemoCustomerSeeder::class,
            DemoInventorySeeder::class,
            DemoSalesSeeder::class,
            DemoOperationsSeeder::class,
            DemoAggregatorSeeder::class,
        ]);
    }
}
