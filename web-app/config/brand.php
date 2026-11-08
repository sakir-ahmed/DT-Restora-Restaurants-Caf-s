<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Brand colours for this deployment
|--------------------------------------------------------------------------
|
| Every customer has their own colours, so they live in .env, not in git.
| Leave a value empty to keep the default from resources/css/app.css (and
| app/Support/Brand.php). Values must be hex colours such as #1f180e.
| Only the production brand reads these; the demo keeps the Datatech palette.
|
*/

return [
    'theme_color' => env('BRAND_THEME_COLOR'),
    'background_color' => env('BRAND_BACKGROUND_COLOR'),

    'light' => [
        'background' => env('BRAND_BACKGROUND'),
        'foreground' => env('BRAND_FOREGROUND'),
        'primary' => env('BRAND_PRIMARY'),
        'primary-foreground' => env('BRAND_PRIMARY_FOREGROUND'),
        'accent' => env('BRAND_ACCENT'),
        'accent-foreground' => env('BRAND_ACCENT_FOREGROUND'),
    ],

    'dark' => [
        'background' => env('BRAND_DARK_BACKGROUND'),
        'foreground' => env('BRAND_DARK_FOREGROUND'),
        'primary' => env('BRAND_DARK_PRIMARY'),
        'primary-foreground' => env('BRAND_DARK_PRIMARY_FOREGROUND'),
        'accent' => env('BRAND_DARK_ACCENT'),
        'accent-foreground' => env('BRAND_DARK_ACCENT_FOREGROUND'),
    ],
];
