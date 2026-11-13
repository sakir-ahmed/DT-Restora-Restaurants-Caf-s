<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Brand of this deployment
|--------------------------------------------------------------------------
|
| The repository ships the DT Restora brand. A customer install (IS_DEMO=false)
| keeps its brand (name, Bangla name, mark shape, colours) in the settings table,
| saved by the customer's seeder and cached. The BRAND_* values below are only a
| fallback for installs that were set up through .env before; a value saved in
| the settings table always wins. IS_DEMO=true always shows the DT Restora brand.
|
| Logo files, wording and seed data live in the git-ignored customer/ folder and
| public/images/customer (see customer/README.md).
|
*/

return [
    // Answers the DatabaseSeeder "which company" question without a terminal: demo, a company slug or skip.
    'seed_profile' => env('SEED_PROFILE'),

    // Where the company seeders live (default database/seeders/production). phpunit.xml points it at an empty folder.
    'production_path' => env('BRAND_PRODUCTION_PATH'),

    // Where the customer files live. phpunit.xml points them at empty folders so tests never read a real customer.
    'customer_path' => env('BRAND_CUSTOMER_PATH'),
    'customer_images' => env('BRAND_CUSTOMER_IMAGES'),

    'name' => env('BRAND_NAME', env('APP_NAME')),
    'name_bn' => env('BRAND_NAME_BN'),
    'mark_shape' => env('BRAND_MARK_SHAPE'),

    'theme_color' => env('BRAND_THEME_COLOR'),
    'background_color' => env('BRAND_BACKGROUND_COLOR'),

    'light' => [
        'background' => env('BRAND_BACKGROUND'),
        'surface' => env('BRAND_SURFACE'),
        'foreground' => env('BRAND_FOREGROUND'),
        'muted' => env('BRAND_MUTED'),
        'border' => env('BRAND_BORDER'),
        'primary' => env('BRAND_PRIMARY'),
        'primary-foreground' => env('BRAND_PRIMARY_FOREGROUND'),
        'accent' => env('BRAND_ACCENT'),
        'accent-foreground' => env('BRAND_ACCENT_FOREGROUND'),
    ],

    'dark' => [
        'background' => env('BRAND_DARK_BACKGROUND'),
        'surface' => env('BRAND_DARK_SURFACE'),
        'foreground' => env('BRAND_DARK_FOREGROUND'),
        'muted' => env('BRAND_DARK_MUTED'),
        'border' => env('BRAND_DARK_BORDER'),
        'primary' => env('BRAND_DARK_PRIMARY'),
        'primary-foreground' => env('BRAND_DARK_PRIMARY_FOREGROUND'),
        'accent' => env('BRAND_DARK_ACCENT'),
        'accent-foreground' => env('BRAND_DARK_ACCENT_FOREGROUND'),
    ],
];
