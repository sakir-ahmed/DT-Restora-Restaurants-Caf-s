<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Qasr Al Mandi: the Saudi Arabian pre-order menu from the printed card. It is a pre-order only
 * category, so it shows in the menu book after the Saudi Arabian introduction page and stays out of
 * the POS, the website menu and the table menu. A dish sold in two sizes (1:2 and 1:4) is one item
 * with two sizes. Safe to run again: it updates by name and never touches the rest of the menu.
 *
 *   php artisan db:seed --class="Database\Seeders\ArmansKitchenQasrAlMandiSeeder" --force
 */
class ArmansKitchenQasrAlMandiSeeder extends Seeder
{
    /**
     * Name, Bangla name, what comes with it, and its sizes (name => price). A dish with one size has no variants.
     *
     * @var list<array{0: string, 1: string, 2: string, 3: array<string, int>}>
     */
    private const DISHES = [
        ['Kabsa', 'কাবসা', 'Chicken + Rice + Batata Maklia + Salad + Dakus Sauce + Thum Sauce (1:4 with Zabadi)', ['1:2' => 900, '1:4' => 1700]],
        ['Dajaj Mandi', 'দাজাজ মান্দি', 'Chicken + Rice + Batata Maklia + Salad + Sauce + Dakus (1:4 with Zabadi)', ['1:2' => 750, '1:4' => 1500]],
        ['Laham Mandi', 'লাহাম মান্দি', 'Mutton + Rice + Batata Maklia + Salad + Sauce + Dakus (1:4 with Zabadi)', ['1:2' => 2100, '1:4' => 4000]],
        ['Al Faham Sada', 'আল ফাহাম সাদা', 'Chicken + Batata Maklia + Dakus Sauce + Thum Sauce', ['1:2' => 500, '1:4' => 950]],
        ['Al Faham Wajba', 'আল ফাহাম ওয়াজবা', 'Chicken + Rice + Batata Maklia + Salad + Dakus Sauce + Thum Sauce', ['1:2' => 900, '1:4' => 1700]],
        ['Ruz Bil Halib (4 Cup)', 'রুজ বিল হালিব (৪ কাপ)', 'Rice with Milk', ['' => 500]],
        ['Harish (Per Person)', 'হারিশ (প্রতি জন)', '', ['' => 250]],
    ];

    public function run(): void
    {
        $branch = Branch::query()->where('code', 'AK01')->first()
            ?? Branch::query()->orderBy('id')->firstOrFail();

        DB::transaction(function () use ($branch): void {
            $category = MenuCategory::query()->withoutGlobalScopes()->updateOrCreate(
                ['branch_id' => $branch->id, 'name' => 'Qasr Al Mandi'],
                [
                    'name_bn' => 'কাসর আল মান্দি',
                    'sort_order' => (int) MenuCategory::query()->withoutGlobalScopes()->where('branch_id', $branch->id)->max('sort_order') + 1,
                    'is_active' => true,
                    'is_preorder_only' => true,
                ],
            );

            foreach (self::DISHES as $order => [$name, $nameBn, $description, $sizes]) {
                $variants = array_filter($sizes, fn (int $price, string $size): bool => $size !== '', ARRAY_FILTER_USE_BOTH);

                $item = MenuItem::query()->withoutGlobalScopes()->updateOrCreate(
                    ['branch_id' => $branch->id, 'name' => $name],
                    [
                        'menu_category_id' => $category->id,
                        'name_bn' => $nameBn,
                        'description' => $description !== '' ? $description : null,
                        'base_price' => min($sizes),
                        'is_available' => true,
                        'sort_order' => $order + 1,
                    ],
                );

                $position = 0;

                foreach ($variants as $size => $price) {
                    $item->variants()->updateOrCreate(
                        ['name' => $size],
                        ['price' => $price, 'is_default' => $position === 0, 'sort_order' => ++$position],
                    );
                }
            }
        });
    }
}
