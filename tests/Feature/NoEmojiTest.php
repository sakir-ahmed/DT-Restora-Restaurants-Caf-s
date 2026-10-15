<?php

declare(strict_types=1);

/**
 * No emojis or decorative Unicode symbol icons anywhere: UI, lang files,
 * views, code, docs, seeders and commits.
 */
it('keeps the codebase free of emojis and symbol icons', function (string $file): void {
    $content = file_get_contents($file);

    expect($content)->not->toMatch('/[\x{1F000}-\x{1FAFF}]/u')
        ->and($content)->not->toMatch('/[\x{2600}-\x{27BF}]/u')
        ->and($content)->not->toMatch('/[\x{2B00}-\x{2BFF}]/u')
        ->and($content)->not->toMatch('/[\x{25A0}-\x{25FF}]/u')
        ->and($content)->not->toMatch('/\x{FE0F}/u');
})->with(fn (): array => array_values(
    array_filter(
        array_map(
            fn (string $file): string => realpath($file) ?: $file,
            array_merge(
                glob(__DIR__.'/../../app/**/*.php') ?: [],
                glob(__DIR__.'/../../app/*.php') ?: [],
                glob(__DIR__.'/../../database/**/*.php') ?: [],
                glob(__DIR__.'/../../database/*.php') ?: [],
                glob(__DIR__.'/../../lang/*/*.php') ?: [],
                glob(__DIR__.'/../../resources/views/*.blade.php') ?: [],
                glob(__DIR__.'/../../docs/*.md') ?: [],
                glob(__DIR__.'/*.php') ?: [],
            ),
        ),
        fn (string $file): bool => is_file($file),
    ),
));

it('keeps frontend sources free of emojis', function (): void {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(__DIR__.'/../../resources/js', FilesystemIterator::SKIP_DOTS),
    );

    $files = [];

    foreach ($iterator as $info) {
        if ($info->isFile() && $info->getExtension() === 'vue' || $info->isFile() && $info->getExtension() === 'js') {
            $files[] = $info->getPathname();
        }
    }

    foreach ($files as $file) {
        $content = (string) file_get_contents($file);

        expect($content)
            ->not->toMatch('/[\x{1F000}-\x{1FAFF}]/u')
            ->not->toMatch('/[\x{2600}-\x{27BF}]/u')
            ->not->toMatch('/[\x{2B00}-\x{2BFF}]/u')
            ->not->toMatch('/[\x{25A0}-\x{25FF}]/u');
    }

    expect($files)->not->toBeEmpty();
});
