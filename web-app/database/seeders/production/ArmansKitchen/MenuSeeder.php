<?php

declare(strict_types=1);

namespace Database\Seeders\Production\ArmansKitchen;

use App\Actions\Inventory\SaveRecipeAction;
use App\Models\Branch;
use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\IngredientStock;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Unit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The owner's costed menu: one outlet, 6 menu categories, 36 items with their
 * selling price (no variants or add-ons), the ingredient price list and one
 * recipe per item.
 *
 * It first clears the existing menu, ingredients, recipes and the stock records
 * that point at them, and leaves users, roles, settings, orders and accounts
 * alone. It refuses to run if any order line references a menu item.
 *
 * Ingredient prices go straight into the branch stock as the average cost with
 * no quantity in hand, so the recipe cost shows at once and the first purchase
 * averages on top of it. Needs the bootstrap seed (units) to have run first.
 *
 *   php artisan db:seed --class='Database\\Seeders\\Production\\ArmansKitchen\\MenuSeeder'
 */
class MenuSeeder extends Seeder
{
    private const BRANCH_CODE = 'AK01';

    public function run(): void
    {
        DB::transaction(function (): void {
            $this->clear();

            $branch = $this->branch();
            $ingredients = $this->ingredients($branch);

            $this->menu($branch, $ingredients);
        });

        $this->command?->info('Menu, ingredient prices and recipes are seeded.');
    }

    private function clear(): void
    {
        if (DB::table('order_items')->exists()) {
            throw new RuntimeException('Orders already use the current menu. Reset stopped so sales history is not broken.');
        }

        DB::table('media')->where('mediable_type', (new MenuItem)->getMorphClass())->delete();

        foreach ([
            'stock_count_items', 'stock_counts', 'stock_transfer_items', 'stock_transfers', 'wastages',
            'purchase_items', 'purchases', 'stock_movements', 'ingredient_stocks',
            'recipe_items', 'recipes', 'ingredients', 'ingredient_categories',
            'menu_item_addons', 'menu_item_variants', 'menu_items', 'menu_categories',
        ] as $table) {
            DB::table($table)->delete();
        }
    }

    private function branch(): Branch
    {
        return Branch::query()->updateOrCreate(['code' => self::BRANCH_CODE], [
            'name' => "Arman's Kitchen",
            'name_bn' => 'আরমানস কিচেন',
            'phone' => '01777656561',
            'address' => 'House 133, Road No. 06, Mohammadia Housing Society, Mohammadpur',
            'address_bn' => 'বাসা ১৩৩, রোড ০৬, মোহাম্মদিয়া হাউজিং সোসাইটি, মোহাম্মদপুর',
            'city' => 'Dhaka',
            'city_bn' => 'ঢাকা',
            'vat_percent' => '0.00',
            'vat_inclusive' => false,
            'service_charge_percent' => '0.00',
            'online_ordering_enabled' => true,
            'opening_hours' => [
                ['days' => ['sat', 'sun', 'mon', 'tue', 'wed', 'thu', 'fri'], 'opens' => '08:00', 'closes' => '23:00'],
            ],
            'receipt_footer' => "Thank you for dining with Arman's Kitchen. For any inquiry: 01777656561",
            'is_active' => true,
        ]);
    }

    /**
     * @return array<string, array{model: Ingredient, unit: Unit}>
     */
    private function ingredients(Branch $branch): array
    {
        $units = Unit::query()->get()->keyBy('symbol');

        $categories = [];

        foreach (MenuCatalog::ingredientCategories() as $category) {
            $categories[$category['name']] = IngredientCategory::query()->updateOrCreate(
                ['name' => $category['name']],
                ['name_bn' => $category['name_bn'], 'sort_order' => $category['sort_order'], 'is_active' => true],
            );
        }

        $ingredients = [];

        foreach (MenuCatalog::ingredients() as $key => $row) {
            $unit = $units[$row['unit']];

            $ingredient = Ingredient::query()->updateOrCreate(['name' => $row['name']], [
                'name_bn' => $row['name_bn'],
                'unit_id' => $unit->id,
                'ingredient_category_id' => $categories[$row['category']]->id,
                'reorder_level' => 0,
                'is_active' => true,
            ]);

            // Prices are per gram, millilitre or piece; stock cost is per base unit (litre for liquids).
            $costPerBaseUnit = $row['unit'] === 'l' ? $row['price'] * 1000 : $row['price'];

            IngredientStock::query()->updateOrCreate(
                ['branch_id' => $branch->id, 'ingredient_id' => $ingredient->id],
                ['stock_qty' => 0, 'avg_cost' => $costPerBaseUnit, 'last_cost' => $costPerBaseUnit],
            );

            $ingredients[$key] = ['model' => $ingredient, 'unit' => $unit];
        }

        return $ingredients;
    }

    /**
     * @param  array<string, array{model: Ingredient, unit: Unit}>  $ingredients
     */
    private function menu(Branch $branch, array $ingredients): void
    {
        $saveRecipe = app(SaveRecipeAction::class);
        $order = 0;

        foreach (MenuCatalog::menu() as $categoryIndex => $categoryData) {
            $category = MenuCategory::query()->withoutGlobalScopes()->updateOrCreate(
                ['branch_id' => $branch->id, 'name' => $categoryData['name']],
                ['name_bn' => $categoryData['name_bn'], 'sort_order' => $categoryIndex + 1, 'is_active' => true],
            );

            foreach ($categoryData['items'] as [$name, $nameBn, $price, $recipe]) {
                $item = MenuItem::query()->withoutGlobalScopes()->updateOrCreate(
                    ['branch_id' => $branch->id, 'name' => $name],
                    [
                        'menu_category_id' => $category->id,
                        'name_bn' => $nameBn,
                        'base_price' => $price,
                        'is_available' => true,
                        'sort_order' => ++$order,
                    ],
                );

                $lines = [];

                foreach ($recipe as $key => $qty) {
                    // Recipe quantities are base units: grams and pieces as given, litres for liquids.
                    $lines[] = [
                        'ingredient_id' => $ingredients[$key]['model']->id,
                        'qty' => $ingredients[$key]['unit']->symbol === 'l' ? $qty / 1000 : $qty,
                    ];
                }

                $saveRecipe->execute($item, $lines);
            }
        }
    }
}
