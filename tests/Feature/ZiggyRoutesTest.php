<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

it('keeps the generated frontend route list in step with the admin and web routes', function (): void {
    $generated = file_get_contents(resource_path('js/ziggy.js'));

    $missing = collect(Route::getRoutes()->getRoutesByName())
        ->keys()
        ->filter(fn (string $name): bool => str_starts_with($name, 'admin.') || str_starts_with($name, 'web.'))
        ->reject(fn (string $name): bool => str_contains($generated, '"'.$name.'":{"uri"'))
        ->values()
        ->all();

    expect($missing)->toBe([], 'Run php artisan ziggy:generate resources/js/ziggy.js');
});
