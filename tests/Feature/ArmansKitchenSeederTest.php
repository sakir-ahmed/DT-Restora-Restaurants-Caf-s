<?php

declare(strict_types=1);

use App\Models\Branch;
use App\Models\Media;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use Database\Seeders\ArmansKitchenDataSeeder;
use Database\Seeders\Support\ArmansKitchenCatalog;

it("seeds Arman's Kitchen with its menu, variants and photos", function (): void {
    $this->seed(ArmansKitchenDataSeeder::class);

    $branch = Branch::query()->where('code', 'AK01')->firstOrFail();
    $items = MenuItem::query()->withoutGlobalScopes()->where('branch_id', $branch->id)->get();

    expect($branch->name)->toBe("Arman's Kitchen")
        ->and($branch->phone)->toBe('01777656561')
        ->and(MenuCategory::query()->withoutGlobalScopes()->where('branch_id', $branch->id)->count())->toBe(8)
        ->and($items)->toHaveCount(45)
        ->and($items->pluck('barcode')->unique())->toHaveCount(45);

    $kabsa = $items->firstWhere('name', 'Kabsa');

    expect($kabsa->variants->pluck('price')->map(fn ($price) => (int) $price)->all())->toBe([900, 1700])
        ->and((int) $items->firstWhere('name', 'Student Burger')->base_price)->toBe(70)
        ->and(storage_path('app/public/'.$items->firstWhere('name', 'Student Burger')->photo_path))->toBeFile();
});

it('is safe to run twice', function (): void {
    $this->seed(ArmansKitchenDataSeeder::class);
    $this->seed(ArmansKitchenDataSeeder::class);

    expect(Branch::query()->where('code', 'AK01')->count())->toBe(1)
        ->and(MenuItem::query()->withoutGlobalScopes()->count())->toBe(45)
        ->and(Media::query()->where('mediable_type', 'branch')->count())->toBe(count(ArmansKitchenCatalog::gallery()));
});

it('ships every photo the catalog and gallery refer to', function (): void {
    foreach (ArmansKitchenCatalog::categories() as $category) {
        foreach ($category['items'] as $item) {
            if ($item['photo'] !== null) {
                expect(base_path("database/seeders/assets/armans-kitchen/menu/{$item['photo']}.webp"))->toBeFile();
            }
        }
    }

    foreach (ArmansKitchenCatalog::gallery() as $entry) {
        expect(base_path("database/seeders/assets/armans-kitchen/gallery/{$entry['file']}.webp"))->toBeFile();
    }
});
