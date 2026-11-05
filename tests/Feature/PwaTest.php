<?php

declare(strict_types=1);

use App\Models\Setting;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    config(['app.is_demo' => false]);
});

it('lists installable icons that exist, including a maskable one, for each mode', function (string $mode): void {
    config(['app.is_demo' => $mode === 'demo']);

    $icons = collect($this->get('/manifest.webmanifest')->assertOk()->json('icons'));

    foreach ($icons as $icon) {
        expect(public_path(ltrim($icon['src'], '/')))->toBeFile();
    }

    $sizes = $icons->where('type', 'image/png')->pluck('sizes')->all();

    expect($sizes)->toContain('192x192', '512x512')
        ->and($icons->where('purpose', 'maskable')->count())->toBeGreaterThanOrEqual(1);
})->with(['production', 'demo']);

it('describes an installable standalone app with shortcuts inside its scope', function (): void {
    $manifest = $this->get('/manifest.webmanifest')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/manifest+json')
        ->json();

    expect($manifest)
        ->toMatchArray(['id' => '/', 'scope' => '/', 'start_url' => '/admin/pos', 'display' => 'standalone'])
        ->and($manifest['shortcuts'])->toHaveCount(3)
        ->and(collect($manifest['shortcuts'])->pluck('url')->all())->each->toStartWith('/admin');
});

it('links the manifest and the iOS standalone tags from every page', function (): void {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('<link rel="manifest" href="/manifest.webmanifest">', false)
        ->assertSee('name="mobile-web-app-capable"', false)
        ->assertSee('name="apple-mobile-web-app-capable"', false)
        ->assertSee('name="apple-mobile-web-app-title"', false);
});

it('shows a self-contained offline page in both languages without starting a session', function (): void {
    $response = $this->get(route('web.offline'))
        ->assertOk()
        ->assertSee('You are offline')
        ->assertSee('আপনি অফলাইনে আছেন')
        ->assertSee('href="/admin/pos"', false)
        ->assertHeader('Cache-Control', 'max-age=300, public');

    expect($response->headers->getCookies())->toBeEmpty()
        ->and($response->getContent())->not->toContain('<script src=');
});

it('keeps the offline page reachable while the public website is switched off', function (): void {
    Setting::current()->update(['website_enabled' => false]);

    $this->get(route('web.offline'))->assertOk();
});

it('serves the service worker from the root without caching and without cookies', function (): void {
    $path = public_path('build/sw.js');
    $created = ! is_file($path);

    if ($created) {
        File::ensureDirectoryExists(dirname($path));
        File::put($path, '// test worker');
    }

    try {
        $response = $this->get('/sw.js')
            ->assertOk()
            ->assertHeader('Service-Worker-Allowed', '/');

        expect($response->headers->get('Content-Type'))->toContain('javascript')
            ->and($response->headers->get('Cache-Control'))->toContain('no-cache')->toContain('no-store')
            ->and($response->headers->getCookies())->toBeEmpty();
    } finally {
        if ($created) {
            File::delete($path);
        }
    }
});

it('answers 404 for the service worker before the app has been built', function (): void {
    $path = public_path('build/sw.js');

    if (is_file($path)) {
        $this->markTestSkipped('A built service worker exists on this machine.');
    }

    $this->get('/sw.js')->assertNotFound();
});
