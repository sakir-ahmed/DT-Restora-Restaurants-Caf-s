<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The look of the site for the current mode. IS_DEMO=false is the production
 * restaurant (Arman's Kitchen); IS_DEMO=true is the neutral DT Restora demo.
 *
 * Each mode has its own files:
 * - public/images/{mode}/brand: emblem, favicons and logos
 * - public/images/{mode}/og-default.png: the default social preview image
 * - lang/demo: demo wording that replaces the production text (see AppServiceProvider)
 * - database/seeders/assets/{mode}: source photos for the seeders
 */
final class Brand
{
    public const PRODUCTION = 'production';

    public const DEMO = 'demo';

    /**
     * @var array<string, array<string, mixed>>
     */
    private const PROFILES = [
        self::PRODUCTION => [
            'name' => "Arman's Kitchen",
            'emblem' => 'emblem-192.png',
            'logo' => 'emblem-512.png',
            'wordmark' => 'wordmark-gold.png',
            'appleIcon' => 'apple-touch-icon.png',
            'favicons' => [
                ['file' => 'favicon.ico', 'type' => 'image/x-icon', 'sizes' => '32x32'],
                ['file' => 'favicon.png', 'type' => 'image/png', 'sizes' => '96x96'],
            ],
            'icons' => [
                ['file' => 'emblem-192.png', 'sizes' => '192x192', 'type' => 'image/png'],
                ['file' => 'emblem-512.png', 'sizes' => '512x512', 'type' => 'image/png'],
            ],
            'themeColor' => '#17120b',
            'backgroundColor' => '#0e0b07',
            'markShape' => 'rounded-full',
        ],
        self::DEMO => [
            'name' => 'DT Restora',
            'emblem' => 'logo-mark.svg',
            'logo' => 'logo-512.png',
            'wordmark' => null,
            'appleIcon' => 'logo-512.png',
            'favicons' => [
                ['file' => 'favicon.svg', 'type' => 'image/svg+xml', 'sizes' => 'any'],
                ['file' => 'favicon.ico', 'type' => 'image/x-icon', 'sizes' => '32x32'],
            ],
            'icons' => [
                ['file' => 'logo-512.png', 'sizes' => '512x512', 'type' => 'image/png'],
                ['file' => 'favicon.svg', 'sizes' => 'any', 'type' => 'image/svg+xml'],
            ],
            'themeColor' => '#0b63ce',
            'backgroundColor' => '#f5f7fa',
            'markShape' => 'rounded-none',
        ],
    ];

    public static function mode(): string
    {
        return config('app.is_demo') === true ? self::DEMO : self::PRODUCTION;
    }

    public static function isDemo(): bool
    {
        return self::mode() === self::DEMO;
    }

    /**
     * URL of a file in the current mode's brand folder.
     */
    public static function asset(string $file): string
    {
        return asset('images/'.self::mode().'/brand/'.$file);
    }

    /**
     * Absolute path of a file in the current mode's brand folder.
     */
    public static function path(string $file): string
    {
        return public_path('images/'.self::mode().'/brand/'.$file);
    }

    public static function ogImage(): string
    {
        return asset('images/'.self::mode().'/og-default.png');
    }

    public static function logo(): string
    {
        return self::asset(self::profile()['logo']);
    }

    public static function themeColor(): string
    {
        return self::hexOrNull(self::isDemo() ? null : config('brand.theme_color')) ?? self::profile()['themeColor'];
    }

    public static function backgroundColor(): string
    {
        return self::hexOrNull(self::isDemo() ? null : config('brand.background_color')) ?? self::profile()['backgroundColor'];
    }

    /**
     * CSS that overrides the production palette with the colours set in .env
     * (config/brand.php). Empty when nothing is set or in demo mode. Only valid
     * hex colours are printed, so a bad value can never inject CSS.
     */
    public static function paletteCss(): string
    {
        if (self::isDemo()) {
            return '';
        }

        $blocks = [];

        foreach (['light' => 'html:root', 'dark' => 'html.dark:root'] as $scheme => $selector) {
            $rules = [];

            foreach ((array) config('brand.'.$scheme, []) as $token => $value) {
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

    private static function hexOrNull(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^#[0-9a-fA-F]{3,8}$/', $value) === 1 ? $value : null;
    }

    /**
     * Folder with the demo wording that replaces lang/{locale} text in demo mode.
     */
    public static function languageOverridePath(): string
    {
        return lang_path(self::mode());
    }

    /**
     * Link tags for the browser tab icon and the home screen icon.
     *
     * @return list<array{rel: string, href: string, type?: string, sizes?: string}>
     */
    public static function iconLinks(): array
    {
        $profile = self::profile();
        $links = array_map(fn (array $icon): array => [
            'rel' => 'icon',
            'href' => self::asset($icon['file']),
            'type' => $icon['type'],
            'sizes' => $icon['sizes'],
        ], $profile['favicons']);

        $links[] = ['rel' => 'apple-touch-icon', 'href' => self::asset($profile['appleIcon'])];

        return $links;
    }

    /**
     * Web app manifest for the POS and the installed app.
     *
     * @return array<string, mixed>
     */
    public static function manifest(): array
    {
        $profile = self::profile();

        return [
            'name' => $profile['name'].' POS',
            'short_name' => $profile['name'],
            'description' => $profile['name'].' restaurant POS',
            'start_url' => '/admin/pos',
            'scope' => '/',
            'display' => 'standalone',
            'theme_color' => self::themeColor(),
            'background_color' => self::backgroundColor(),
            'icons' => array_map(fn (array $icon): array => [
                'src' => '/images/'.self::mode().'/brand/'.$icon['file'],
                'sizes' => $icon['sizes'],
                'type' => $icon['type'],
                'purpose' => 'any',
            ], $profile['icons']),
        ];
    }

    /**
     * What the Vue components need to draw the mark and wordmark.
     *
     * @return array{mode: string, name: string, emblem: string|null, wordmark: string|null, markShape: string}
     */
    public static function shared(): array
    {
        $profile = self::profile();

        return [
            'mode' => self::mode(),
            'name' => __('site.brand_name'),
            'emblem' => self::asset($profile['emblem']),
            'wordmark' => $profile['wordmark'] ? self::asset($profile['wordmark']) : null,
            'markShape' => $profile['markShape'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function profile(): array
    {
        return self::PROFILES[self::mode()];
    }
}
