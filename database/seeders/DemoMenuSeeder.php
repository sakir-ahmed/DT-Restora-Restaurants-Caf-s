<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Media;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemAddOn;
use App\Models\MenuItemVariant;
use App\Models\RestaurantTable;
use Database\Seeders\Support\DemoMenuCatalog;
use Database\Seeders\Support\DemoPhotos;
use Illuminate\Database\Seeder;

class DemoMenuSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('testing')) {
            return;
        }

        $factors = [
            'BR01' => 1.0,
            'BR02' => 0.92,
            'BR03' => 0.88,
        ];

        $tables = [
            'BR01' => [
                ['prefix' => 'T', 'from' => 1, 'to' => 12, 'seats' => 4, 'area' => 'Ground floor'],
                ['prefix' => 'F', 'from' => 1, 'to' => 6, 'seats' => 6, 'area' => 'Family'],
                ['prefix' => 'R', 'from' => 1, 'to' => 4, 'seats' => 4, 'area' => 'Rooftop'],
            ],
            'BR02' => [
                ['prefix' => 'H', 'from' => 1, 'to' => 10, 'seats' => 4, 'area' => 'Hall'],
                ['prefix' => 'C', 'from' => 1, 'to' => 4, 'seats' => 6, 'area' => 'Cabin'],
            ],
            'BR03' => [
                ['prefix' => 'U', 'from' => 1, 'to' => 8, 'seats' => 4, 'area' => 'Floor'],
                ['prefix' => 'G', 'from' => 1, 'to' => 4, 'seats' => 4, 'area' => 'Garden'],
            ],
        ];

        $catalog = DemoMenuCatalog::categories();

        foreach (Branch::query()->whereIn('code', array_keys($factors))->orderBy('code')->get() as $branch) {
            $factor = $factors[$branch->code];
            $keptCategories = [];
            $barcode = 1;

            foreach ($catalog as $categoryOrder => $categoryData) {
                $category = MenuCategory::query()->updateOrCreate(
                    ['branch_id' => $branch->id, 'name' => $categoryData['name']],
                    [
                        'name_bn' => $categoryData['name_bn'],
                        'sort_order' => $categoryOrder + 1,
                        'is_active' => true,
                    ],
                );
                $keptCategories[] = $category->id;

                foreach ($categoryData['items'] as $itemOrder => $itemData) {
                    $item = MenuItem::query()->updateOrCreate(
                        ['branch_id' => $branch->id, 'name' => $itemData['name']],
                        [
                            'menu_category_id' => $category->id,
                            'name_bn' => $itemData['name_bn'],
                            'description' => $itemData['description'],
                            'description_bn' => $itemData['description_bn'],
                            'base_price' => $this->price((int) $itemData['price'], $factor),
                            'photo_path' => DemoPhotos::publish((string) $itemData['photo']),
                            'barcode' => sprintf('DT-%s-%04d', $branch->code, $barcode),
                            'is_available' => ! $itemData['sold_out'],
                            'is_vegetarian' => $itemData['veg'],
                            'sort_order' => $itemOrder + 1,
                        ],
                    );
                    $barcode++;

                    $item->variants()->delete();
                    $item->addOns()->delete();

                    foreach ($itemData['variants'] as $variantOrder => $variant) {
                        MenuItemVariant::query()->create([
                            'menu_item_id' => $item->id,
                            'name' => $variant['name'],
                            'name_bn' => $variant['name_bn'],
                            'price' => $this->price((int) $variant['price'], $factor),
                            'is_default' => $variant['default'],
                            'sort_order' => $variantOrder + 1,
                        ]);
                    }

                    foreach ($itemData['addons'] as $addOnOrder => $addOn) {
                        MenuItemAddOn::query()->create([
                            'menu_item_id' => $item->id,
                            'name' => $addOn['name'],
                            'name_bn' => $addOn['name_bn'],
                            'price' => $this->price((int) $addOn['price'], $factor),
                            'is_active' => true,
                            'sort_order' => $addOnOrder + 1,
                        ]);
                    }

                    $photo = $item->photo_path;

                    if ($photo !== null) {
                        Media::query()->updateOrCreate(
                            [
                                'mediable_type' => $item->getMorphClass(),
                                'mediable_id' => $item->id,
                                'kind' => Media::KIND_IMAGE,
                            ],
                            [
                                'path' => $photo,
                                'title' => $item->name,
                                'title_bn' => $item->name_bn,
                                'sort_order' => 1,
                                'is_active' => true,
                            ],
                        );
                    }
                }
            }

            MenuCategory::query()
                ->where('branch_id', $branch->id)
                ->whereNotIn('id', $keptCategories)
                ->whereDoesntHave('items')
                ->delete();

            foreach ($tables[$branch->code] ?? [] as $group) {
                foreach (range($group['from'], $group['to']) as $number) {
                    RestaurantTable::query()->updateOrCreate(
                        ['branch_id' => $branch->id, 'name' => $group['prefix'].$number],
                        [
                            'seats' => $group['seats'],
                            'area' => $group['area'],
                            'status' => 'free',
                            'is_active' => true,
                        ],
                    );
                }
            }
        }
    }

    private function price(int $amount, float $factor): string
    {
        $adjusted = (int) (round(($amount * $factor) / 10) * 10);

        return number_format((float) max(20, $adjusted), 2, '.', '');
    }
}
