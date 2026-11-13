<?php

declare(strict_types=1);

use Database\Seeders\Production\DtRestora\MenuCatalog;
use Database\Seeders\Production\DtRestora\Photos;

it('ships a full demo menu and a photograph for every dish', function () {
    $items = collect(MenuCatalog::categories())->flatMap(fn (array $category): array => $category['items']);

    expect($items->count())->toBeGreaterThan(70)
        ->and($items->pluck('name')->unique()->count())->toBe($items->count());

    $items->each(function (array $item): void {
        expect($item['price'])->toBeGreaterThan(0)
            ->and($item['name_bn'])->not->toBe('')
            ->and(Photos::source((string) $item['photo']))->not->toBeNull();
    });
});
