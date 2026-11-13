<?php

declare(strict_types=1);

namespace Database\Seeders\Production\SampleCafe;

use App\Models\Branch;
use Database\Seeders\Support\BaseCompanySeeder;

/**
 * A fake company for the DatabaseSeeder tests.
 */
class CompanySeeder extends BaseCompanySeeder
{
    public function run(): void
    {
        $this->saveBrand(['name' => 'Sample Cafe', 'name_bn' => 'স্যাম্পল ক্যাফে', 'theme_color' => '#123456', 'light' => ['primary' => '#123456']]);

        Branch::query()->create(['name' => 'Sample Cafe', 'code' => 'SC01', 'city' => 'Dhaka', 'vat_percent' => '0.00', 'service_charge_percent' => '0.00', 'is_active' => true]);
    }
}
