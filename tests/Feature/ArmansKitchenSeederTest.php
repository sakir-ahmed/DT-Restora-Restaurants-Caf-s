<?php

declare(strict_types=1);

use App\Models\AggregatorChannel;
use App\Models\BlogPost;
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
        ->and(MenuCategory::query()->withoutGlobalScopes()->where('branch_id', $branch->id)->count())->toBe(9)
        ->and($items)->toHaveCount(55)
        ->and($items->pluck('barcode')->unique())->toHaveCount(55);

    $kabsa = $items->firstWhere('name', 'Kabsa');

    expect($kabsa->variants->pluck('price')->map(fn ($price) => (int) $price)->all())->toBe([900, 1700])
        ->and((int) $items->firstWhere('name', 'Student Burger')->base_price)->toBe(70)
        ->and(storage_path('app/public/'.$items->firstWhere('name', 'Student Burger')->photo_path))->toBeFile();
});

it('publishes journal posts with covers and gives dishes more than one photo', function (): void {
    $this->seed(ArmansKitchenDataSeeder::class);

    $posts = BlogPost::query()->get();

    expect($posts)->toHaveCount(count(ArmansKitchenCatalog::posts()))
        ->and($posts->every(fn (BlogPost $post): bool => $post->status === BlogPost::STATUS_PUBLISHED && storage_path('app/public/'.$post->cover_path) !== '' && is_file(storage_path('app/public/'.$post->cover_path))))->toBeTrue();

    $sada = MenuItem::query()->withoutGlobalScopes()->where('name', 'Al Faham Sada')->firstOrFail();

    expect(Media::query()->where('mediable_type', 'menu_item')->where('mediable_id', $sada->id)->count())->toBeGreaterThan(1);
});

it('is safe to run twice', function (): void {
    $this->seed(ArmansKitchenDataSeeder::class);
    $this->seed(ArmansKitchenDataSeeder::class);

    expect(Branch::query()->where('code', 'AK01')->count())->toBe(1)
        ->and(MenuItem::query()->withoutGlobalScopes()->count())->toBe(55)
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

    foreach (ArmansKitchenCatalog::posts() as $post) {
        expect(base_path("database/seeders/assets/armans-kitchen/blog/{$post['cover']}.webp"))->toBeFile();
    }

    foreach (ArmansKitchenCatalog::gallery() as $entry) {
        expect(base_path("database/seeders/assets/armans-kitchen/gallery/{$entry['file']}.webp"))->toBeFile();
    }
});

it('adds the delivery channels switched off and keeps breakfast edits', function (): void {
    $this->seed(ArmansKitchenDataSeeder::class);

    expect(AggregatorChannel::query()->where('is_active', false)->pluck('key')->sort()->values()->all())->toBe(['foodi', 'foodpanda', 'pathao_food']);

    $parota = MenuItem::query()->withoutGlobalScopes()->where('name', 'Parota')->firstOrFail();

    expect($parota->is_available)->toBeFalse();

    $parota->update(['base_price' => 15, 'is_available' => true]);

    $this->seed(ArmansKitchenDataSeeder::class);

    expect((int) $parota->fresh()->base_price)->toBe(15)
        ->and($parota->fresh()->is_available)->toBeTrue();
});
