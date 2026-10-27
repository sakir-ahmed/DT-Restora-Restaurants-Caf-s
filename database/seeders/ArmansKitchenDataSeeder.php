<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\AggregatorChannel;
use App\Models\Branch;
use App\Models\Media;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemVariant;
use Database\Seeders\Support\ArmansKitchenCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Real data for Arman's Kitchen, Mohammadpur: the outlet, the printed menu
 * with photographs, the Qasr Al Mandi pre-order range and the website gallery.
 *
 * Run it on top of the bootstrap seed:
 *   php artisan db:seed --class=ArmansKitchenDataSeeder
 *
 * It is safe to run again; records are matched by code, name or path.
 */
class ArmansKitchenDataSeeder extends Seeder
{
    private const BRANCH_CODE = 'AK01';

    private const ASSETS = 'database/seeders/assets/armans-kitchen';

    public function run(): void
    {
        DB::transaction(function (): void {
            $branch = $this->branch();

            $this->menu($branch);
            $this->gallery($branch);
            $this->deliveryChannels();
        });

        $this->command?->info("Arman's Kitchen branch, menu and gallery are seeded.");
    }

    private function branch(): Branch
    {
        return Branch::query()->updateOrCreate(['code' => self::BRANCH_CODE], [
            'name' => "Arman's Kitchen",
            'name_bn' => 'আরমানস কিচেন',
            'phone' => '01777656561',
            'address' => 'House 133, Road No. 06, Mohammadia Housing Society, Mohammadpur',
            'city' => 'Dhaka',
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

    private function menu(Branch $branch): void
    {
        $keptCategories = [];

        foreach (ArmansKitchenCatalog::categories() as $categoryOrder => $categoryData) {
            $category = MenuCategory::query()->withoutGlobalScopes()->updateOrCreate(
                ['branch_id' => $branch->id, 'name' => $categoryData['name']],
                ['name_bn' => $categoryData['name_bn'], 'sort_order' => $categoryOrder + 1, 'is_active' => true],
            );
            $keptCategories[] = $category->id;

            foreach ($categoryData['items'] as $itemData) {
                $attributes = ['branch_id' => $branch->id, 'name' => $itemData['name']];
                $values = [
                    'menu_category_id' => $category->id,
                    'name_bn' => $itemData['name_bn'],
                    'description' => $itemData['description'],
                    'photo_path' => $this->publishMenuPhoto($itemData['photo']),
                    'barcode' => sprintf('AK-%04d', $itemData['number']),
                    'base_price' => $itemData['price'],
                    'is_available' => $itemData['available'],
                    'is_vegetarian' => $itemData['veg'],
                    'sort_order' => $itemData['number'],
                ];

                $item = ($categoryData['seed_once'] ?? false)
                    ? MenuItem::query()->withoutGlobalScopes()->firstOrCreate($attributes, $values)
                    : MenuItem::query()->withoutGlobalScopes()->updateOrCreate($attributes, $values);

                $item->variants()->delete();

                foreach ($itemData['variants'] as $variantOrder => $variant) {
                    MenuItemVariant::query()->create([
                        'menu_item_id' => $item->id,
                        'name' => $variant['name'],
                        'name_bn' => $variant['name_bn'],
                        'price' => $variant['price'],
                        'is_default' => $variant['default'],
                        'sort_order' => $variantOrder + 1,
                    ]);
                }

                if ($item->photo_path !== null) {
                    Media::query()->updateOrCreate(
                        ['mediable_type' => $item->getMorphClass(), 'mediable_id' => $item->id, 'kind' => Media::KIND_IMAGE],
                        ['path' => $item->photo_path, 'title' => $item->name, 'title_bn' => $item->name_bn, 'sort_order' => 1, 'is_active' => true],
                    );
                }
            }
        }

        MenuCategory::query()
            ->withoutGlobalScopes()
            ->where('branch_id', $branch->id)
            ->whereNotIn('id', $keptCategories)
            ->whereDoesntHave('items')
            ->delete();
    }

    /**
     * The delivery platforms printed on the menu cards. They start switched off
     * with no commission, ready to be set up in the admin panel.
     */
    private function deliveryChannels(): void
    {
        foreach (['pathao_food' => 'Pathao Food', 'foodpanda' => 'Foodpanda', 'foodi' => 'Foodi'] as $key => $name) {
            AggregatorChannel::query()->firstOrCreate(
                ['key' => $key],
                ['name' => $name, 'commission_percent' => '0.00', 'is_active' => false],
            );
        }
    }

    private function gallery(Branch $branch): void
    {
        foreach (ArmansKitchenCatalog::gallery() as $order => $entry) {
            $path = $this->publish("gallery/{$entry['file']}.webp", "gallery/armans-kitchen/{$entry['file']}.webp");

            Media::query()->updateOrCreate(
                ['mediable_type' => $branch->getMorphClass(), 'mediable_id' => $branch->id, 'path' => $path],
                ['kind' => Media::KIND_IMAGE, 'title' => $entry['title'], 'title_bn' => $entry['title_bn'], 'sort_order' => $order + 1, 'is_active' => true],
            );
        }
    }

    private function publishMenuPhoto(?string $name): ?string
    {
        return $name === null ? null : $this->publish("menu/{$name}.webp", "menu/armans-kitchen/{$name}.webp");
    }

    /**
     * Copy a processed photo onto the public disk and return its storage-relative path.
     */
    private function publish(string $source, string $relative): ?string
    {
        $from = base_path(self::ASSETS.'/'.$source);

        if (! is_file($from)) {
            return null;
        }

        $to = storage_path('app/public/'.$relative);

        File::ensureDirectoryExists(dirname($to));

        if (! is_file($to) || filemtime($from) > filemtime($to)) {
            File::copy($from, $to);
        }

        return $relative;
    }
}
