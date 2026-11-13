<?php

declare(strict_types=1);

namespace Database\Seeders\Production\DtRestora;

use Database\Seeders\Support\BaseCompanySeeder;

/**
 * DT Restora itself: the demo company. It seeds sample branches, staff, menu,
 * customers, inventory, sales and delivery channels, and keeps the default DT
 * Restora brand. It runs when IS_DEMO=true, and as the "DT Restora (Demo)"
 * option on a production install.
 */
class CompanySeeder extends BaseCompanySeeder
{
    public static function label(): string
    {
        return 'DT Restora (Demo)';
    }

    public static function slug(): string
    {
        return 'demo';
    }

    public function run(): void
    {
        // The default brand has no files of its own; this clears any other company's runtime files.
        $this->saveBrand(null);
        $this->installFiles();

        $this->call([
            BranchSeeder::class,
            StaffSeeder::class,
            MenuSeeder::class,
            CustomerSeeder::class,
            InventorySeeder::class,
            SalesSeeder::class,
            OperationsSeeder::class,
            AggregatorSeeder::class,
        ]);
    }
}
