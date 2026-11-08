<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Sales\OpenShiftAction;
use App\Models\Branch;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Small outlet used by the Playwright offline cashier journey.
 * It is not part of the demo dataset.
 */
class E2ePosSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            ChartOfAccountsSeeder::class,
            UnitSeeder::class,
        ]);

        Setting::query()->create([
            'discount_limit_percent' => '10.00',
            'wastage_spike_multiplier' => '1.50',
            'pos_pin_minutes' => 120,
            'delivery_otp_enabled' => false,
        ]);

        $branch = Branch::factory()->create([
            'name' => 'Gulshan',
            'code' => 'BR01',
            'phone' => '01711000001',
            'address' => 'Road 12, Gulshan',
            'vat_percent' => '0.00',
            'vat_inclusive' => false,
            'service_charge_percent' => '0.00',
            'receipt_footer' => 'Thank you',
        ]);

        $category = MenuCategory::factory()->create([
            'branch_id' => $branch->id,
            'name' => 'Drinks',
            'is_active' => true,
        ]);

        MenuItem::factory()->create([
            'branch_id' => $branch->id,
            'menu_category_id' => $category->id,
            'name' => 'Tea',
            'base_price' => '120.00',
            'is_available' => true,
        ]);

        $cashier = User::factory()->role('cashier', $branch->id)->create([
            'name' => 'Rifat Hasan',
            'username' => 'cashier',
        ]);

        app(OpenShiftAction::class)->execute($branch, $cashier, 500, 'T01');
    }
}
