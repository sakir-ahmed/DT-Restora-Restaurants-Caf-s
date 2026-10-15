<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemAddOn;
use App\Models\MenuItemVariant;
use App\Models\RestaurantTable;
use Illuminate\Database\Seeder;

class DemoMenuSeeder extends Seeder
{
    public function run(): void
    {
        if (config('app.env') === 'testing') {
            return;
        }

        $branch = Branch::query()->where('code', 'BR01')->firstOrFail();

        $menu = [
            'Rice and Curry' => ['name_bn' => 'ভাত ও তরকারি', 'items' => [
                ['name' => 'Chicken Biryani', 'name_bn' => 'চিকেন বিরিয়ানি', 'price' => '280.00', 'veg' => false, 'variants' => [
                    ['name' => 'Regular', 'name_bn' => 'রেগুলার', 'price' => '280.00', 'default' => true],
                    ['name' => 'Large', 'name_bn' => 'লার্জ', 'price' => '360.00', 'default' => false],
                ], 'addons' => [
                    ['name' => 'Extra Roast', 'name_bn' => 'অতিরিক্ত রোস্ট', 'price' => '90.00'],
                    ['name' => 'Borhani Glass', 'name_bn' => 'বোরহানি গ্লাস', 'price' => '40.00'],
                ]],
                ['name' => 'Beef Tehari', 'name_bn' => 'বিফ তেহারি', 'price' => '250.00', 'veg' => false, 'variants' => [
                    ['name' => 'Regular', 'name_bn' => 'রেগুলার', 'price' => '250.00', 'default' => true],
                    ['name' => 'Large', 'name_bn' => 'লার্জ', 'price' => '330.00', 'default' => false],
                ], 'addons' => []],
            ]],
            'Fast Food' => ['name_bn' => 'ফাস্ট ফুড', 'items' => [
                ['name' => 'Chicken Burger', 'name_bn' => 'চিকেন বার্গার', 'price' => '180.00', 'veg' => false, 'variants' => [
                    ['name' => 'Single', 'name_bn' => 'সিঙ্গেল', 'price' => '180.00', 'default' => true],
                    ['name' => 'Double Patty', 'name_bn' => 'ডাবল প্যাটি', 'price' => '280.00', 'default' => false],
                ], 'addons' => [
                    ['name' => 'Cheese Slice', 'name_bn' => 'চিজ স্লাইস', 'price' => '40.00'],
                    ['name' => 'Fries Regular', 'name_bn' => 'ফ্রাই রেগুলার', 'price' => '70.00'],
                ]],
                ['name' => 'Vegetable Sandwich', 'name_bn' => 'ভেজিটেবল স্যান্ডউইচ', 'price' => '120.00', 'veg' => true, 'variants' => [], 'addons' => []],
            ]],
            'Drinks' => ['name_bn' => 'পানীয়', 'items' => [
                ['name' => 'Lacchi Sharbat', 'name_bn' => 'লাচ্ছি শরবত', 'price' => '60.00', 'veg' => true, 'variants' => [
                    ['name' => 'Normal', 'name_bn' => 'সাধারণ', 'price' => '60.00', 'default' => true],
                    ['name' => 'Special', 'name_bn' => 'স্পেশাল', 'price' => '90.00', 'default' => false],
                ], 'addons' => []],
                ['name' => 'Masala Tea', 'name_bn' => 'মসলা চা', 'price' => '25.00', 'veg' => true, 'variants' => [], 'addons' => []],
            ]],
        ];

        foreach ($menu as $categoryName => $categoryData) {
            $category = MenuCategory::query()->updateOrCreate(
                ['branch_id' => $branch->id, 'name' => $categoryName],
                ['name_bn' => $categoryData['name_bn'], 'is_active' => true],
            );

            foreach ($categoryData['items'] as $index => $itemData) {
                $item = MenuItem::query()->updateOrCreate(
                    ['branch_id' => $branch->id, 'menu_category_id' => $category->id, 'name' => $itemData['name']],
                    [
                        'name_bn' => $itemData['name_bn'],
                        'base_price' => $itemData['price'],
                        'is_available' => true,
                        'is_vegetarian' => $itemData['veg'],
                        'sort_order' => $index + 1,
                    ],
                );

                foreach ($itemData['variants'] as $variantOrder => $variantData) {
                    MenuItemVariant::query()->updateOrCreate(
                        ['menu_item_id' => $item->id, 'name' => $variantData['name']],
                        [
                            'name_bn' => $variantData['name_bn'],
                            'price' => $variantData['price'],
                            'is_default' => $variantData['default'],
                            'sort_order' => $variantOrder + 1,
                        ],
                    );
                }

                foreach ($itemData['addons'] as $addOnOrder => $addOnData) {
                    MenuItemAddOn::query()->updateOrCreate(
                        ['menu_item_id' => $item->id, 'name' => $addOnData['name']],
                        [
                            'name_bn' => $addOnData['name_bn'],
                            'price' => $addOnData['price'],
                            'sort_order' => $addOnOrder + 1,
                        ],
                    );
                }
            }
        }

        // Restaurant tables for dine-in at BR01.
        foreach (range(1, 8) as $number) {
            RestaurantTable::query()->updateOrCreate(
                ['branch_id' => $branch->id, 'name' => 'T'.$number],
                ['seats' => $number <= 4 ? 4 : 6, 'area' => $number <= 4 ? 'Ground floor' : 'Rooftop', 'status' => 'free', 'is_active' => true],
            );
        }

        foreach (['Head Chef' => 45000, 'Sous Chef' => 30000, 'Kitchen Helper' => 15000, 'Cleaner' => 12000] as $designation => $salary) {
            Employee::query()->updateOrCreate(
                ['branch_id' => $branch->id, 'designation' => $designation],
                [
                    'name' => fake()->name(),
                    'monthly_salary' => $salary,
                    'is_active' => true,
                ],
            );
        }
    }
}
