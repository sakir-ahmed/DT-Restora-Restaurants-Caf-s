<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Branch;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Application bootstrap data always seeds. Branches, menu, staff and the
     * rest of the sample data seed only when IS_DEMO=true.
     *
     * This production branch is Arman's Kitchen: on a fresh install (no branch
     * yet) the real outlet, menu and gallery seed too. Later runs leave them
     * alone so edits made in the admin panel are never overwritten. To
     * re-sync from the catalog on purpose, run:
     *   php artisan db:seed --class=ArmansKitchenDataSeeder --force
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
            if (Branch::query()->doesntExist()) {
                $this->call(ArmansKitchenDataSeeder::class);
            }

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
