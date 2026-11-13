<?php

declare(strict_types=1);

use App\Support\Brand;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->customerRoot = storage_path('framework/testing/customer-'.uniqid());
    $this->customerImages = $this->customerRoot.'/public';

    File::ensureDirectoryExists($this->customerImages.'/brand');

    config([
        'brand.customer_path' => $this->customerRoot,
        'brand.customer_images' => $this->customerImages,
    ]);
});

afterEach(function (): void {
    File::deleteDirectory($this->customerRoot);
});

it('takes the name and colours of a customer install from .env', function (): void {
    Brand::save([
        'name' => 'Hills Table',
        'name_bn' => 'হিলস টেবিল',
        'theme_color' => '#112233',
        'light' => ['primary' => '#445566'],
        'dark' => ['primary' => '#778899'],
    ]);

    expect(Brand::name())->toBe('Hills Table')
        ->and(Brand::nameBn())->toBe('হিলস টেবিল')
        ->and(Brand::manifest()['short_name'])->toBe('Hills Table')
        ->and(Brand::manifest()['theme_color'])->toBe('#112233')
        ->and(Brand::paletteCss())->toContain('--color-primary:#445566')->toContain('--color-primary:#778899');

    $this->get('/')->assertSee('Hills Table', false)->assertSee('--color-primary:#445566', false);
    $this->withSession(['locale' => 'bn'])->get('/')->assertSee('হিলস টেবিল', false);
});

it('shows the English name when a customer has no Bangla name', function (): void {
    Brand::save(['name' => 'Hills Table']);

    expect(Brand::nameBn())->toBe('Hills Table');
});

it('ignores bad colour values so no CSS can be injected', function (): void {
    Brand::save(['light' => ['primary' => 'red;}body{display:none', 'accent' => '#abc']]);

    expect(Brand::paletteCss())->toBe('html:root{--color-accent:#abc}');
});

it('never applies customer brand values in demo mode', function (): void {
    Brand::save(['name' => 'Hills Table', 'light' => ['primary' => '#445566']]);
    config(['app.is_demo' => true]);

    expect(Brand::name())->toBe('DT Restora')
        ->and(Brand::paletteCss())->toBe('')
        ->and(Brand::manifest()['theme_color'])->toBe('#0b63ce');
});

it('uses a customer logo set and falls back per file to the DT Restora files', function (): void {
    File::put($this->customerImages.'/brand/logo-192.png', 'x');
    File::put($this->customerImages.'/brand/favicon.ico', 'x');
    File::put($this->customerImages.'/og-default.png', 'x');

    expect(Brand::shared()['emblem'])->toEndWith('/images/customer/brand/logo-192.png')
        ->and(Brand::logo())->toEndWith('/images/default/brand/logo-512.png')
        ->and(Brand::ogImage())->toEndWith('/images/customer/og-default.png')
        ->and(collect(Brand::iconLinks())->where('rel', 'icon')->pluck('href')->all())->each->toContain('/images/customer/brand/favicon.ico')
        ->and(Brand::path('favicon.ico'))->toBe($this->customerImages.'/brand/favicon.ico');

    config(['app.is_demo' => true]);

    expect(Brand::shared()['emblem'])->toEndWith('/images/default/brand/logo-mark.svg')
        ->and(Brand::ogImage())->toEndWith('/images/default/og-default.png');
});

it('reports what a customer install still takes from the defaults', function (): void {
    $this->artisan('brand:status')->expectsOutputToContain('DT Restora default')->assertSuccessful();

    Brand::save(['name' => 'Hills Table']);

    $ready = collect(Brand::status())->firstWhere('item', 'Name');

    expect($ready['ready'])->toBeTrue()
        ->and(collect(Brand::status())->firstWhere('item', 'Restaurant data')['ready'])->toBeFalse();
});

it('layers customer wording on top of the neutral text', function (): void {
    File::ensureDirectoryExists($this->customerRoot.'/lang/en');
    File::put($this->customerRoot.'/lang/en/errors.php', "<?php\n\nreturn ['503_message' => 'Back soon.'];\n");

    expect(flat_translations('en', ['errors'])['errors.503_message'])->toBe('Back soon.');

    config(['app.is_demo' => true]);

    expect(flat_translations('en', ['errors'])['errors.503_message'])->not->toBe('Back soon.');
});
