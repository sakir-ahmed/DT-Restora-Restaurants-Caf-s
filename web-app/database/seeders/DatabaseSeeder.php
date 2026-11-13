<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Branch;
use App\Support\ProductionCompanies;
use Database\Seeders\Production\DtRestora\CompanySeeder as DtRestoraSeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public const SKIP = 'skip';

    /**
     * The bootstrap data (roles, chart of accounts, units, payment methods,
     * settings, root administrator) always seeds. Then the company:
     *
     * - IS_DEMO=true: DT Restora, the demo company.
     * - IS_DEMO=false: a customer install. It asks which company this install is
     *   for: DT Restora (Demo), one folder of database/seeders/production, or skip.
     *   It only asks while there is no branch, so admin-panel edits are never
     *   overwritten. Set SEED_PROFILE (demo, a company slug or skip) to answer
     *   without a terminal. To re-sync one company on purpose:
     *   php artisan db:seed --class="Database\Seeders\Production\ArmansKitchen\CompanySeeder" --force
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
            $this->call(DtRestoraSeeder::class);

            return;
        }

        $this->chooseCompany();
    }

    private function chooseCompany(): void
    {
        if (Branch::query()->exists()) {
            return;
        }

        $companies = ProductionCompanies::all();
        $options = array_map(fn (array $company): string => $company['label'], $companies) + [self::SKIP => 'Skip, set everything up later in the admin panel'];

        $this->command?->newLine();
        $this->command?->warn('This install has no branch yet.');

        $profile = (string) config('brand.seed_profile');
        $default = array_key_exists($profile, $options) ? $profile : self::SKIP;
        $asks = $this->command !== null && ! $this->command->option('no-interaction');
        $choice = $asks ? $this->command->choice('Which company is this install for?', $options, $default) : $default;

        if (isset($companies[$choice])) {
            $this->call($companies[$choice]['class']);

            return;
        }

        $this->command?->info('Skipped. Sign in as admin and add the first branch under Branches.');
    }
}
