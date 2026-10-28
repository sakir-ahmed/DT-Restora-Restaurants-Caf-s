<?php

declare(strict_types=1);

use App\Models\Branch;
use App\Models\MenuItem;
use App\Support\Brand;
use Database\Seeders\DatabaseSeeder;

it('has brand files, source photos and wording for both modes', function (string $mode): void {
    foreach (['favicon.ico'] as $file) {
        expect(public_path("images/{$mode}/brand/{$file}"))->toBeFile();
    }

    expect(public_path("images/{$mode}/og-default.png"))->toBeFile()
        ->and(base_path("database/seeders/assets/{$mode}"))->toBeDirectory()
        ->and(base_path("docs/{$mode}"))->toBeDirectory();
})->with([Brand::PRODUCTION, Brand::DEMO]);

it('seeds the production restaurant when IS_DEMO is false', function (): void {
    config(['app.is_demo' => false]);

    $this->seed(DatabaseSeeder::class);

    expect(Branch::query()->pluck('code')->all())->toBe(['AK01'])
        ->and(MenuItem::query()->withoutGlobalScopes()->where('name', 'Kabsa')->exists())->toBeTrue();
});

it('keeps existing branches when seeding production again', function (): void {
    config(['app.is_demo' => false]);

    $this->seed(DatabaseSeeder::class);
    Branch::query()->where('code', 'AK01')->update(['name' => 'Edited in admin']);
    $this->seed(DatabaseSeeder::class);

    expect(Branch::query()->where('code', 'AK01')->value('name'))->toBe('Edited in admin');
});

it('seeds the demo branches when IS_DEMO is true', function (): void {
    config(['app.is_demo' => true]);

    $this->seed(DatabaseSeeder::class);

    expect(Branch::query()->pluck('code')->all())->toContain('BR01', 'BR02')
        ->and(Branch::query()->where('code', 'AK01')->exists())->toBeFalse();
});

it('describes the brand of the current mode', function (): void {
    expect(Brand::mode())->toBe('production')
        ->and(Brand::manifest()['short_name'])->toBe("Arman's Kitchen")
        ->and(Brand::manifest()['theme_color'])->toBe('#17120b');

    config(['app.is_demo' => true]);

    expect(Brand::mode())->toBe('demo')
        ->and(Brand::manifest()['short_name'])->toBe('DT Restora')
        ->and(Brand::ogImage())->toEndWith('/images/demo/og-default.png')
        ->and(Brand::logo())->toEndWith('/images/demo/brand/logo-512.png');
});

it('serves the manifest and favicon of the current mode', function (): void {
    $this->get('/manifest.webmanifest')->assertOk()->assertJsonPath('short_name', "Arman's Kitchen");
    $this->get('/favicon.ico')->assertOk();

    config(['app.is_demo' => true]);

    $this->get('/manifest.webmanifest')->assertJsonPath('short_name', 'DT Restora');
    $this->get('/')->assertSee('data-brand="demo"', false)->assertSee('/images/demo/brand/favicon.svg', false);
});

it('shows neutral demo wording instead of the production text', function (): void {
    $production = flat_translations('en', ['site']);

    config(['app.is_demo' => true]);
    app('translation.loader')->addPath(Brand::languageOverridePath());
    app('translator')->setLocale('en');

    $demo = flat_translations('en', ['site']);

    expect($production['site.brand_name'])->toBe("Arman's Kitchen")
        ->and($demo['site.brand_name'])->toBe('DT Restora')
        ->and($demo['site.hero_title'])->not->toBe($production['site.hero_title'])
        ->and(__('site.brand_name'))->toBe('DT Restora');
});
