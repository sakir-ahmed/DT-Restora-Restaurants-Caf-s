<?php

declare(strict_types=1);

use App\Models\AggregatorChannel;
use App\Models\BlogPost;
use App\Models\Branch;
use App\Models\Media;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use Database\Seeders\ProductionDataSeeder;
use Database\Seeders\Support\ProductionCatalog;

it("seeds Arman's Kitchen with its menu, variants and photos", function (): void {
    $this->seed(ProductionDataSeeder::class);

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
    $this->seed(ProductionDataSeeder::class);

    $posts = BlogPost::query()->get();

    expect($posts)->toHaveCount(count(ProductionCatalog::posts()))
        ->and($posts->every(fn (BlogPost $post): bool => $post->status === BlogPost::STATUS_PUBLISHED && storage_path('app/public/'.$post->cover_path) !== '' && is_file(storage_path('app/public/'.$post->cover_path))))->toBeTrue();

    $sada = MenuItem::query()->withoutGlobalScopes()->where('name', 'Al Faham Sada')->firstOrFail();

    expect(Media::query()->where('mediable_type', 'menu_item')->where('mediable_id', $sada->id)->count())->toBeGreaterThan(1);
});

it('is safe to run twice', function (): void {
    $this->seed(ProductionDataSeeder::class);
    $this->seed(ProductionDataSeeder::class);

    expect(Branch::query()->where('code', 'AK01')->count())->toBe(1)
        ->and(MenuItem::query()->withoutGlobalScopes()->count())->toBe(55)
        ->and(Media::query()->where('mediable_type', 'branch')->count())->toBe(count(ProductionCatalog::gallery()));
});

it('ships every photo the catalog and gallery refer to', function (): void {
    foreach (ProductionCatalog::categories() as $category) {
        foreach ($category['items'] as $item) {
            if ($item['photo'] !== null) {
                expect(base_path("database/seeders/assets/production/menu/{$item['photo']}.webp"))->toBeFile();
            }
        }
    }

    foreach (ProductionCatalog::posts() as $post) {
        expect(base_path("database/seeders/assets/production/blog/{$post['cover']}.webp"))->toBeFile();
    }

    foreach (ProductionCatalog::gallery() as $entry) {
        expect(base_path("database/seeders/assets/production/gallery/{$entry['file']}.webp"))->toBeFile();
    }
});

it('adds the delivery channels switched off and keeps breakfast edits', function (): void {
    $this->seed(ProductionDataSeeder::class);

    expect(AggregatorChannel::query()->where('is_active', false)->pluck('key')->sort()->values()->all())->toBe(['foodi', 'foodpanda', 'pathao_food']);

    $parota = MenuItem::query()->withoutGlobalScopes()->where('name', 'Parota')->firstOrFail();

    expect($parota->is_available)->toBeFalse();

    $parota->update(['base_price' => 15, 'is_available' => true]);

    $this->seed(ProductionDataSeeder::class);

    expect((int) $parota->fresh()->base_price)->toBe(15)
        ->and($parota->fresh()->is_available)->toBeTrue();
});

it('describes the priced dishes in English and Bangla', function (): void {
    $this->seed(ProductionDataSeeder::class);

    $items = MenuItem::query()->withoutGlobalScopes()->where('base_price', '>', 0)->get();

    expect($items->filter(fn (MenuItem $item): bool => blank($item->description) || blank($item->description_bn)))->toBeEmpty();

    $kabsa = $items->firstWhere('name', 'Kabsa');

    expect($kabsa->description_bn)->toContain('বাতাতা মাকলিয়া')->toContain('প্রি-অর্ডার')
        ->and($items->firstWhere('name', 'Shahi Chotpoti (AK Special)')->description)->toContain('crisp fried onion');
});

it('keeps the legal pages in step between English and Bangla', function (): void {
    $english = require lang_path('en/legal.php');
    $bangla = require lang_path('bn/legal.php');

    expect(array_map('array_keys', array_filter($bangla, 'is_array')))->toBe(array_map('array_keys', array_filter($english, 'is_array')))
        ->and(json_encode($bangla, JSON_UNESCAPED_UNICODE))->toContain('০১৭৭৭৬৫৬৫৬১')
        ->and(json_encode($english))->not->toContain('SSLCommerz');
});
