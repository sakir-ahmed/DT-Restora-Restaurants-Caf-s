<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * The look of the site. The repository ships the DT Restora brand and nothing
 * else: its own name, Datatech blue palette, logo and neutral wording.
 *
 * - IS_DEMO=true: always the DT Restora brand.
 * - IS_DEMO=false: a customer install. Its company seeder in
 *   database/seeders/production saves the brand (name, colours) in the settings
 *   table, cached, and installs the logo set and wording. Anything the company
 *   did not provide falls back to the DT Restora default, so a fresh install
 *   always looks complete. BRAND_* values in .env are only an old fallback.
 *
 * Runtime files (written by the company seeder, git-ignored):
 * - public/images/default/brand: the DT Restora logo set (tracked)
 * - public/images/customer/brand: the company logo set
 * - customer/lang/{locale}: company wording on top of lang/{locale}
 *
 * The company logo set uses the same file names as the default set, plus the
 * optional logo-wordmark.png, apple-touch-icon.png, favicon.png. See
 * database/seeders/production/README.md.
 */
final class Brand
{
    public const DEMO = 'demo';

    public const CUSTOMER = 'customer';

    private const CACHE_KEY = 'brand.settings';

    public const DEFAULT_NAME = 'DT Restora';

    public const DEFAULT_NAME_BN = 'ডিটি রেস্টোরা';

    public const DEFAULT_THEME_COLOR = '#0b63ce';

    public const DEFAULT_BACKGROUND_COLOR = '#f5f7fa';

    private const MARK_SHAPES = ['rounded-none', 'rounded-md', 'rounded-xl', 'rounded-full'];

    public static function mode(): string
    {
        return config('app.is_demo') === true ? self::DEMO : self::CUSTOMER;
    }

    public static function isDemo(): bool
    {
        return self::mode() === self::DEMO;
    }

    /**
     * Whether the name is the customer's own rather than the DT Restora default.
     */
    public static function hasCustomName(): bool
    {
        return ! self::isDemo() && self::configured('name') !== null && self::configured('name') !== self::DEFAULT_NAME;
    }

    public static function name(): string
    {
        return self::isDemo() ? self::DEFAULT_NAME : (self::configured('name') ?? self::DEFAULT_NAME);
    }

    /**
     * The name in Bangla. A customer without a Bangla name shows the English one
     * instead of the DT Restora Bangla name.
     */
    public static function nameBn(): string
    {
        if (self::isDemo()) {
            return self::DEFAULT_NAME_BN;
        }

        return self::configured('name_bn') ?? (self::hasCustomName() ? self::name() : self::DEFAULT_NAME_BN);
    }

    /**
     * URL of a file in the brand folder (the customer's copy when there is one).
     */
    public static function asset(string $file): string
    {
        return asset('images/'.self::resolve([$file]));
    }

    /**
     * Absolute path of a file in the brand folder (the customer's copy when there is one).
     */
    public static function path(string $file): string
    {
        $resolved = self::resolve([$file]);

        return str_starts_with($resolved, 'customer/')
            ? self::customerImagePath(substr($resolved, strlen('customer/')))
            : public_path('images/'.$resolved);
    }

    public static function ogImage(): string
    {
        if (! self::isDemo() && is_file(self::customerImagePath('og-default.png'))) {
            return asset('images/customer/og-default.png');
        }

        return asset('images/default/og-default.png');
    }

    public static function logo(): string
    {
        return self::asset('logo-512.png');
    }

    public static function themeColor(): string
    {
        return self::hexOrNull(self::isDemo() ? null : self::configured('theme_color')) ?? self::DEFAULT_THEME_COLOR;
    }

    public static function backgroundColor(): string
    {
        return self::hexOrNull(self::isDemo() ? null : self::configured('background_color')) ?? self::DEFAULT_BACKGROUND_COLOR;
    }

    /**
     * Accent colour for the offline page on a dark background.
     */
    public static function darkAccentColor(): string
    {
        return self::hexOrNull(self::isDemo() ? null : (self::tokens('dark')['primary'] ?? null)) ?? '#38a8ff';
    }

    /**
     * CSS that overrides the default DT Restora palette with the colours set in
     * .env (config/brand.php). Empty when nothing is set or in demo mode. Only
     * valid hex colours are printed, so a bad value can never inject CSS.
     */
    public static function paletteCss(): string
    {
        if (self::isDemo()) {
            return '';
        }

        $blocks = [];

        foreach (['light' => 'html:root', 'dark' => 'html.dark:root'] as $scheme => $selector) {
            $rules = [];

            foreach (self::tokens($scheme) as $token => $value) {
                $hex = self::hexOrNull($value);

                if ($hex !== null) {
                    $rules[] = '--color-'.$token.':'.$hex;
                }
            }

            if ($rules !== []) {
                $blocks[] = $selector.'{'.implode(';', $rules).'}';
            }
        }

        return implode('', $blocks);
    }

    /**
     * A path inside the customer runtime folder (git-ignored, written by the company seeder).
     */
    public static function customerPath(string $relative = ''): string
    {
        $root = config('brand.customer_path') ?: base_path('customer');

        return rtrim((string) $root, '/').($relative === '' ? '' : '/'.$relative);
    }

    /**
     * A path inside the customer's public image folder (git-ignored).
     */
    public static function customerImagePath(string $relative = ''): string
    {
        $root = config('brand.customer_images') ?: public_path('images/customer');

        return rtrim((string) $root, '/').($relative === '' ? '' : '/'.$relative);
    }

    private static function hexOrNull(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^#[0-9a-fA-F]{3,8}$/', $value) === 1 ? $value : null;
    }

    /**
     * The brand saved in the settings table (see Setting::$brand), cached for all
     * requests. Empty when nothing is saved or the table is not migrated yet.
     *
     * @return array<string, mixed>
     */
    public static function settings(): array
    {
        try {
            return Cache::rememberForever(self::CACHE_KEY, fn (): array => (array) (Setting::query()->value('brand') ?? []));
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Drop the cached brand. Setting does this on every save.
     */
    public static function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Save the brand of this install in the settings table (null clears it back
     * to the DT Restora default). The cache is refreshed by the model.
     *
     * @param  array<string, mixed>|null  $brand
     */
    public static function save(?array $brand): void
    {
        Setting::current()->update(['brand' => $brand]);
    }

    /**
     * A text value: the settings table first, then the legacy BRAND_* .env value.
     */
    private static function configured(string $key): ?string
    {
        foreach ([self::settings()[$key] ?? null, config('brand.'.$key)] as $value) {
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    /**
     * The colour tokens of one scheme: the legacy .env values with the settings
     * table on top.
     *
     * @return array<string, mixed>
     */
    private static function tokens(string $scheme): array
    {
        return array_filter(
            array_merge((array) config('brand.'.$scheme, []), (array) (self::settings()[$scheme] ?? [])),
            fn (mixed $value): bool => is_string($value) && trim($value) !== '',
        );
    }

    /**
     * Folder with the customer wording that replaces lang/{locale} text when
     * IS_DEMO=false. It may not exist.
     */
    public static function languageOverridePath(): string
    {
        return self::customerPath('lang');
    }

    /**
     * Whether this install has at least one customer company folder (database/seeders/production).
     */
    public static function hasCustomerSeed(): bool
    {
        return ProductionCompanies::customers() !== [];
    }

    /**
     * Link tags for the browser tab icon and the home screen icon.
     *
     * @return list<array{rel: string, href: string, type?: string, sizes?: string}>
     */
    public static function iconLinks(): array
    {
        $links = array_map(fn (array $icon): array => [
            'rel' => 'icon',
            'href' => asset('images/'.$icon['path']),
            'type' => $icon['type'],
            'sizes' => $icon['sizes'],
        ], self::favicons());

        $links[] = ['rel' => 'apple-touch-icon', 'href' => asset('images/'.self::resolve(['apple-touch-icon.png', 'logo-512.png']))];

        return $links;
    }

    /**
     * The tab icons. A customer that supplies any favicon gets only its own,
     * because browsers prefer the SVG and would otherwise hide a customer .ico.
     *
     * @return list<array{path: string, type: string, sizes: string}>
     */
    private static function favicons(): array
    {
        $known = [
            'favicon.svg' => ['image/svg+xml', 'any'],
            'favicon.ico' => ['image/x-icon', '32x32'],
            'favicon.png' => ['image/png', '96x96'],
        ];

        $customer = self::isDemo() ? [] : array_filter(array_keys($known), fn (string $file): bool => is_file(self::customerImagePath('brand/'.$file)));
        $icons = [];

        foreach ($customer !== [] ? $customer : ['favicon.ico', 'favicon.svg'] as $file) {
            $icons[] = [
                'path' => $customer !== [] ? 'customer/brand/'.$file : 'default/brand/'.$file,
                'type' => $known[$file][0],
                'sizes' => $known[$file][1],
            ];
        }

        return $icons;
    }

    /**
     * Web app manifest for the POS and the installed app.
     *
     * @return array<string, mixed>
     */
    public static function manifest(): array
    {
        $name = self::name();

        return [
            'id' => '/',
            'name' => $name.' POS',
            'short_name' => $name,
            'description' => $name.' restaurant POS that keeps working without internet',
            'start_url' => '/admin/pos',
            'scope' => '/',
            'display' => 'standalone',
            'display_override' => ['standalone', 'minimal-ui'],
            'orientation' => 'any',
            'categories' => ['business', 'food', 'productivity'],
            'theme_color' => self::themeColor(),
            'background_color' => self::backgroundColor(),
            'prefer_related_applications' => false,
            'icons' => self::manifestIcons(),
            'shortcuts' => self::shortcuts(),
        ];
    }

    /**
     * @return list<array{src: string, sizes: string, type: string, purpose: string}>
     */
    private static function manifestIcons(): array
    {
        return array_map(fn (array $icon): array => [
            'src' => '/images/'.self::resolve([$icon['file']]),
            'sizes' => $icon['sizes'],
            'type' => 'image/png',
            'purpose' => $icon['purpose'],
        ], [
            ['file' => 'logo-192.png', 'sizes' => '192x192', 'purpose' => 'any'],
            ['file' => 'logo-512.png', 'sizes' => '512x512', 'purpose' => 'any'],
            ['file' => 'logo-maskable-512.png', 'sizes' => '512x512', 'purpose' => 'maskable'],
        ]);
    }

    /**
     * Quick actions offered by the installed app icon. Each opens a page behind
     * its own permission, so staff without access get the normal denied page.
     *
     * @return list<array{name: string, short_name: string, url: string, icons: list<array{src: string, sizes: string, type: string}>}>
     */
    private static function shortcuts(): array
    {
        $icons = [['src' => '/images/'.self::resolve(['logo-192.png']), 'sizes' => '192x192', 'type' => 'image/png']];

        return [
            ['name' => __('nav.pos'), 'short_name' => __('nav.pos'), 'url' => '/admin/pos', 'icons' => $icons],
            ['name' => __('nav.orders'), 'short_name' => __('nav.orders'), 'url' => '/admin/orders', 'icons' => $icons],
            ['name' => __('nav.kds'), 'short_name' => __('nav.kds'), 'url' => '/admin/kds', 'icons' => $icons],
        ];
    }

    /**
     * What the Vue components need to draw the mark and wordmark.
     *
     * @return array{mode: string, name: string, emblem: string, wordmark: string|null, markShape: string}
     */
    public static function shared(): array
    {
        $wordmark = ! self::isDemo() && is_file(self::customerImagePath('brand/logo-wordmark.png'));
        $shape = self::configured('mark_shape');

        return [
            'mode' => self::mode(),
            'name' => __('site.brand_name'),
            'emblem' => asset('images/'.self::resolve(['logo-mark.svg', 'logo-192.png'])),
            'wordmark' => $wordmark ? asset('images/customer/brand/logo-wordmark.png') : null,
            'markShape' => ! self::isDemo() && in_array($shape, self::MARK_SHAPES, true) ? $shape : 'rounded-none',
        ];
    }

    /**
     * What a customer install still shows from the DT Restora defaults. Used by
     * the seeder and `php artisan brand:status` to tell the installer what to set.
     *
     * @return list<array{item: string, ready: bool, hint: string}>
     */
    public static function status(): array
    {
        $colours = array_filter(array_merge(self::tokens('light'), self::tokens('dark')), fn (mixed $value): bool => self::hexOrNull($value) !== null);

        return [
            ['item' => 'Name', 'ready' => self::hasCustomName(), 'hint' => 'Save the brand name in the settings table (a company seeder or Brand::save()).'],
            ['item' => 'Colours', 'ready' => $colours !== [], 'hint' => 'Save the brand colours in the settings table (a company seeder or Brand::save()).'],
            ['item' => 'Logo and icons', 'ready' => is_file(self::customerImagePath('brand/logo-512.png')), 'hint' => 'Add a brand folder to the company seeder (see database/seeders/production/README.md).'],
            ['item' => 'Social preview image', 'ready' => is_file(self::customerImagePath('og-default.png')), 'hint' => 'Add og-default.png (1200x630) to the company folder.'],
            ['item' => 'Wording', 'ready' => is_dir(self::languageOverridePath()), 'hint' => 'Add a lang folder to the company folder for the website text.'],
            ['item' => 'Restaurant data', 'ready' => self::hasCustomerSeed(), 'hint' => 'Add a company folder in database/seeders/production (see its README.md).'],
        ];
    }

    /**
     * Resolve the first candidate file to a path under public/images. A customer
     * file wins over the default one; demo mode never reads the customer folder.
     *
     * @param  non-empty-list<string>  $candidates
     */
    private static function resolve(array $candidates): string
    {
        if (! self::isDemo()) {
            foreach ($candidates as $file) {
                if (is_file(self::customerImagePath('brand/'.$file))) {
                    return 'customer/brand/'.$file;
                }
            }
        }

        foreach ($candidates as $file) {
            if (is_file(public_path('images/default/brand/'.$file))) {
                return 'default/brand/'.$file;
            }
        }

        return 'default/brand/'.$candidates[array_key_last($candidates)];
    }
}
