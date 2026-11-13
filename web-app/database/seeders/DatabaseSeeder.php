<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Branch;
use App\Support\Brand;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * The bootstrap data (roles, chart of accounts, units, payment methods,
     * settings, root administrator) always seeds. The rest depends on IS_DEMO:
     *
     * - IS_DEMO=true: the DT Restora demo, with sample branches, staff, menu,
     *   customers, inventory, sales and delivery channels.
     * - IS_DEMO=false: a customer install. The restaurant, menu and gallery come
     *   from the customer's own git-ignored seeder (customer/seed/CustomerDataSeeder.php)
     *   and only on a fresh install (no branch yet), so later runs never overwrite
     *   edits made in the admin panel. Without that file nothing is invented: the
     *   seeder warns and offers to set up the first branch or skip. To re-sync
     *   from the customer data on purpose, run:
     *   php artisan db:seed --class=CustomerDataSeeder --force
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

        if (Branch::query()->exists()) {
            return;
        }

        if (Brand::hasCustomerSeed() && class_exists(CustomerDataSeeder::class)) {
            $this->call(CustomerDataSeeder::class);

            return;
        }

        $this->withoutCustomerData();
    }

    /**
     * No customer seed data: say what is missing and let the installer choose
     * between a quick first branch and skipping it. Without a terminal the
     * answer is always to skip.
     */
    private function withoutCustomerData(): void
    {
        $this->command?->newLine();
        $this->command?->warn('No customer data found. IS_DEMO is false and customer/seed/CustomerDataSeeder.php does not exist,');
        $this->command?->warn('so the database has roles, accounts and settings only: no branch, menu or gallery.');

        foreach (Brand::status() as $row) {
            if (! $row['ready']) {
                $this->command?->line('  Using the DT Restora default for: '.$row['item'].'. '.$row['hint']);
            }
        }

        $choice = $this->command?->choice(
            'What now?',
            ['skip' => 'Skip, set everything up later in the admin panel', 'setup' => 'Set up the first branch now'],
            'skip',
        );

        if ($choice === 'setup') {
            $this->firstBranch();
        } else {
            $this->command?->info('Skipped. Sign in as admin and add the first branch under Branches.');
        }
    }

    private function firstBranch(): void
    {
        $command = $this->command;

        if ($command === null) {
            return;
        }

        $name = (string) $command->ask('Branch name', Brand::name());
        $code = strtoupper((string) $command->ask('Branch code (used on invoices)', 'BR01'));
        $phone = $command->ask('Phone (optional)');
        $address = $command->ask('Address (optional)');
        $city = $command->ask('City', 'Dhaka');

        Branch::query()->create([
            'name' => $name,
            'code' => $code,
            'phone' => $phone,
            'address' => $address,
            'city' => $city,
            'vat_percent' => '0.00',
            'service_charge_percent' => '0.00',
            'is_active' => true,
        ]);

        $command->info("Branch {$name} ({$code}) created. Add its menu in the admin panel.");
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
