<?php

declare(strict_types=1);

use App\Support\Brand;

beforeEach(function (): void {
    // Start from an unbranded config so a developer's own .env colours never change the result.
    config(['app.is_demo' => false, 'brand' => ['theme_color' => null, 'background_color' => null, 'light' => [], 'dark' => []]]);
});

it('prints no palette override until colours are set in the environment', function (): void {
    expect(Brand::paletteCss())->toBe('')
        ->and(Brand::themeColor())->toBe('#17120b');
});

it('overrides the production palette from config for light and dark mode', function (): void {
    config([
        'brand.light.primary' => '#123456',
        'brand.light.accent-foreground' => '#fff',
        'brand.dark.accent' => '#abcdef',
        'brand.theme_color' => '#112233',
        'brand.background_color' => '#445566',
    ]);

    expect(Brand::paletteCss())->toBe('html:root{--color-primary:#123456;--color-accent-foreground:#fff}html.dark:root{--color-accent:#abcdef}')
        ->and(Brand::themeColor())->toBe('#112233')
        ->and(Brand::manifest()['theme_color'])->toBe('#112233')
        ->and(Brand::manifest()['background_color'])->toBe('#445566');
});

it('ignores anything that is not a hex colour so no CSS can be injected', function (): void {
    config(['brand.light.primary' => 'red;}body{display:none']);

    expect(Brand::paletteCss())->toBe('');
});

it('leaves the demo palette alone', function (): void {
    config(['app.is_demo' => true, 'brand.light.primary' => '#123456']);

    expect(Brand::paletteCss())->toBe('')
        ->and(Brand::themeColor())->toBe('#0b63ce');
});

it('adds the palette to the page head', function (): void {
    config(['brand.light.primary' => '#123456']);

    $this->get(route('login'))->assertOk()->assertSee('<style id="brand-palette">html:root{--color-primary:#123456}</style>', false);
});
