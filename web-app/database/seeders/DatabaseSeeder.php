<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Branch;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * The bootstrap data (roles, chart of accounts, units, payment methods,
     * settings, root administrator) always seeds. The rest depends on IS_DEMO:
     *
     * - IS_DEMO=false (production): the real restaurant, menu and gallery, see
     *   ProductionDataSeeder. This only happens on a fresh install (no branch
     *   yet), so later runs never overwrite edits made in the admin panel. To
     *   re-sync from the catalog on purpose, run:
     *   php artisan db:seed --class=ProductionDataSeeder --force
     * - IS_DEMO=true: sample branches, staff, menu, customers, inventory, sales
     *   and delivery channels.
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

        if (config('app.is_demo') === true) {
            $this->call($this->demoSeeders());

            return;
        }

        if (Branch::query()->doesntExist()) {
            $this->call(ProductionDataSeeder::class);
        }
    }

    /**
     * @return list<class-string<Seeder>>
     */
    private function demoSeeders(): array
    {
        return [
            BranchSeeder::class,
            DemoStaffSeeder::class,
            DemoMenuSeeder::class,
            DemoCustomerSeeder::class,
            DemoInventorySeeder::class,
            DemoSalesSeeder::class,
            DemoOperationsSeeder::class,
            DemoAggregatorSeeder::class,
        ];
    }
}
