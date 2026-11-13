<?php

declare(strict_types=1);

use App\Models\Branch;
use App\Support\Brand;
use Database\Seeders\DatabaseSeeder;

it('ships the DT Restora brand files and demo source photos', function (): void {
    expect(public_path('images/default/brand/favicon.ico'))->toBeFile()
        ->and(public_path('images/default/brand/logo-512.png'))->toBeFile()
        ->and(public_path('images/default/og-default.png'))->toBeFile()
        ->and(base_path('database/seeders/production/DtRestora/assets'))->toBeDirectory();
});

it('seeds the demo branches when IS_DEMO is true', function (): void {
    config(['app.is_demo' => true]);

    $this->seed(DatabaseSeeder::class);

    expect(Branch::query()->pluck('code')->all())->toContain('BR01', 'BR02');
});

it('describes the DT Restora brand by default in both modes', function (): void {
    expect(Brand::mode())->toBe('customer')
        ->and(Brand::name())->toBe('DT Restora')
        ->and(Brand::manifest()['short_name'])->toBe('DT Restora')
        ->and(Brand::manifest()['theme_color'])->toBe('#0b63ce');

    config(['app.is_demo' => true]);

    expect(Brand::mode())->toBe('demo')
        ->and(Brand::manifest()['short_name'])->toBe('DT Restora')
        ->and(Brand::ogImage())->toEndWith('/images/default/og-default.png')
        ->and(Brand::logo())->toEndWith('/images/default/brand/logo-512.png');
});

it('serves the DT Restora manifest and favicon', function (): void {
    $this->get('/manifest.webmanifest')->assertOk()->assertJsonPath('short_name', 'DT Restora');
    $this->get('/favicon.ico')->assertOk();
    $this->get('/')->assertSee('/images/default/brand/favicon.svg', false);
});

it('uses the same neutral wording in demo and customer mode', function (): void {
    $customer = flat_translations('en', ['site']);

    config(['app.is_demo' => true]);

    expect($customer['site.brand_name'])->toBe('DT Restora')
        ->and(flat_translations('en', ['site'])['site.brand_name'])->toBe('DT Restora')
        ->and(__('site.brand_name'))->toBe('DT Restora');
});
