<?php

declare(strict_types=1);

use Database\Seeders\Support\DemoMenuCatalog;
use Database\Seeders\Support\DemoPhotos;

it('ships a full demo menu and a photograph for every dish', function () {
    $items = collect(DemoMenuCatalog::categories())->flatMap(fn (array $category): array => $category['items']);

    expect($items->count())->toBeGreaterThan(70)
        ->and($items->pluck('name')->unique()->count())->toBe($items->count());

    $items->each(function (array $item): void {
        expect($item['price'])->toBeGreaterThan(0)
            ->and($item['name_bn'])->not->toBe('')
            ->and(DemoPhotos::source((string) $item['photo']))->not->toBeNull();
    });
});
