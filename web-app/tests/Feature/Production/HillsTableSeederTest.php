<?php

declare(strict_types=1);

use App\Models\Branch;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Support\Brand;
use App\Support\ProductionCompanies;
use Database\Seeders\Production\HillsTable\Catalog;
use Database\Seeders\Production\HillsTable\CompanySeeder;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->runtimeRoot = storage_path('framework/testing/hills-'.uniqid());

    config([
        'brand.production_path' => database_path('seeders/production'),
        'brand.customer_path' => $this->runtimeRoot,
        'brand.customer_images' => $this->runtimeRoot.'/public',
    ]);
});

afterEach(function (): void {
    File::deleteDirectory($this->runtimeRoot);
});

it('lists Hills Table as a company', function (): void {
    expect(ProductionCompanies::customers())->toHaveKey('hills-table')
        ->and(ProductionCompanies::customers()['hills-table']['label'])->toBe('Hills Table');
});

it('seeds the outlet and the whole menu book', function (): void {
    $this->seed(CompanySeeder::class);

    $branch = Branch::query()->where('code', 'HT01')->firstOrFail();

    expect($branch->name)->toBe('Hills Table')
        ->and($branch->phone)->toBe('01793784459')
        ->and($branch->address)->toBe('Tazmahal Road, 26/8, Mohammadpur')
        ->and($branch->receipt_footer)->toContain('01620827893')
        ->and(MenuCategory::query()->withoutGlobalScopes()->count())->toBe(5)
        ->and(MenuItem::query()->withoutGlobalScopes()->count())->toBe(21)
        ->and(MenuItem::query()->withoutGlobalScopes()->sum('base_price'))->toEqual(4220)
        ->and((int) MenuItem::query()->withoutGlobalScopes()->where('name', 'Bhapa Elish')->value('base_price'))->toBe(350)
        ->and(MenuItem::query()->withoutGlobalScopes()->where('name', 'Bini Chaler Pitha')->value('name_bn'))->toBe('বিন্নি চালের পিঠা');
});

it('has every menu number once and a Bangla name for each dish', function (): void {
    $items = collect(Catalog::categories())->flatMap(fn (array $category): array => $category['items']);

    expect($items->pluck('number')->sort()->values()->all())->toBe(range(1, 21))
        ->and($items->filter(fn (array $item): bool => $item['name_bn'] === '')->count())->toBe(0);
});

it('saves the green and orange brand and installs the logo set', function (): void {
    $this->seed(CompanySeeder::class);

    expect(Brand::name())->toBe('Hills Table')
        ->and(Brand::nameBn())->toBe('হিলস টেবিল')
        ->and(Brand::themeColor())->toBe('#0f4a2a')
        ->and(Brand::paletteCss())->toContain('--color-primary:#0f4a2a')->toContain('--color-accent:#c25e00')
        ->and(Brand::logo())->toEndWith('/images/customer/brand/logo-512.png')
        ->and(Brand::ogImage())->toEndWith('/images/customer/og-default.png')
        ->and($this->runtimeRoot.'/public/brand/logo-192.png')->toBeFile()
        ->and($this->runtimeRoot.'/lang/en/site.php')->toBeFile();
});

it('does not duplicate anything when seeded again', function (): void {
    $this->seed(CompanySeeder::class);
    $this->seed(CompanySeeder::class);

    expect(Branch::query()->count())->toBe(1)
        ->and(MenuItem::query()->withoutGlobalScopes()->count())->toBe(21);
});
