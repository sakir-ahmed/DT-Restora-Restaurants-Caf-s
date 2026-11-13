<?php

declare(strict_types=1);

namespace Database\Seeders\Production\HillsTable;

use App\Models\Branch;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use Database\Seeders\Support\BaseCompanySeeder;
use Illuminate\Support\Facades\DB;

/**
 * First setup of Hills Table, Tazmahal Road, Mohammadpur: its brand (taken from
 * the logo: deep green, leaf green and orange), outlet and menu book. The site
 * runs at https://hills-table.datatechbd.com (set APP_URL on that server).
 * DatabaseSeeder lists it as a company when IS_DEMO=false.
 *
 * Run it again to re-sync the outlet and menu from this file:
 *   php artisan db:seed --class="Database\Seeders\Production\HillsTable\CompanySeeder" --force
 *
 * It is safe to run again; records are matched by code, name or category.
 */
class CompanySeeder extends BaseCompanySeeder
{
    private const BRANCH_CODE = 'HT01';

    private const BRAND = [
        'name' => 'Hills Table',
        'name_bn' => 'হিলস টেবিল',
        'mark_shape' => 'rounded-full',
        'theme_color' => '#0f4a2a',
        'background_color' => '#f7faf6',
        'light' => [
            'background' => '#f7faf6', 'surface' => '#ffffff', 'foreground' => '#0f2a1a', 'muted' => '#4d5f53',
            'border' => '#dbe7dc', 'primary' => '#0f4a2a', 'primary-foreground' => '#ffffff',
            'accent' => '#c25e00', 'accent-foreground' => '#ffffff',
        ],
        'dark' => [
            'background' => '#08150d', 'surface' => '#0e2315', 'foreground' => '#eef5ee', 'muted' => '#a9bfae',
            'border' => '#1f3d2a', 'primary' => '#3fbf6a', 'primary-foreground' => '#08150d',
            'accent' => '#f7941d', 'accent-foreground' => '#08150d',
        ],
    ];

    public function run(): void
    {
        $this->saveBrand(self::BRAND);
        $this->installFiles();

        DB::transaction(function (): void {
            $this->menu($this->branch());
        });

        $this->command?->info('Hills Table branch and menu are seeded.');
    }

    private function branch(): Branch
    {
        return Branch::query()->updateOrCreate(['code' => self::BRANCH_CODE], [
            'name' => 'Hills Table',
            'name_bn' => 'হিলস টেবিল',
            'phone' => '01793784459',
            'address' => 'Tazmahal Road, 26/8, Mohammadpur',
            'address_bn' => 'তাজমহল রোড, ২৬/৮, মোহাম্মদপুর',
            'city' => 'Dhaka',
            'city_bn' => 'ঢাকা',
            'vat_percent' => '0.00',
            'vat_inclusive' => false,
            'service_charge_percent' => '0.00',
            'online_ordering_enabled' => true,
            'receipt_footer' => 'Thank you for dining with Hills Table. For any inquiry: 01793784459, 01620827893',
            'is_active' => true,
        ]);
    }

    private function menu(Branch $branch): void
    {
        foreach (Catalog::categories() as $order => $categoryData) {
            $category = MenuCategory::query()->withoutGlobalScopes()->updateOrCreate(
                ['branch_id' => $branch->id, 'name' => $categoryData['name']],
                ['name_bn' => $categoryData['name_bn'], 'sort_order' => $order + 1, 'is_active' => true],
            );

            foreach ($categoryData['items'] as $item) {
                $one = $item['serves'] === 1;

                MenuItem::query()->withoutGlobalScopes()->updateOrCreate(
                    ['branch_id' => $branch->id, 'name' => $item['name']],
                    [
                        'menu_category_id' => $category->id,
                        'name_bn' => $item['name_bn'],
                        'description' => $one ? 'Serves 1 person.' : 'Serves 1 to 2 persons.',
                        'description_bn' => $one ? '১ জনের জন্য।' : '১–২ জনের জন্য।',
                        'barcode' => sprintf('HT-%04d', $item['number']),
                        'base_price' => $item['price'],
                        'is_available' => true,
                        'is_vegetarian' => false,
                        'sort_order' => $item['number'],
                    ],
                );
            }
        }
    }
}
