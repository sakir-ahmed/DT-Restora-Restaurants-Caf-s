<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
});

it('has every English translation key in Bangla', function (): void {
    $english = flat_translations('en');
    $bangla = flat_translations('bn');

    expect($english)->not->toBeEmpty();

    $missing = array_diff(array_keys($english), array_keys($bangla));

    expect($missing)->toBeEmpty();
});

it('has every Bangla translation key in English', function (): void {
    $english = flat_translations('en');
    $bangla = flat_translations('bn');

    $missing = array_diff(array_keys($bangla), array_keys($english));

    expect($missing)->toBeEmpty();
});

it('sets the locale from the user preference first', function (): void {
    $user = User::factory()->role('cashier')->create(['locale' => 'bn']);

    $response = $this->actingAs($user, 'staff')->get('/admin');

    $response->assertOk();
    expect(app()->getLocale())->toBe('bn');
});

it('falls back to the session locale for users without a preference', function (): void {
    $user = User::factory()->role('cashier')->create();
    $user->forceFill(['locale' => null])->save();

    $response = $this->actingAs($user, 'staff')
        ->withSession(['locale' => 'bn'])
        ->get('/admin');

    $response->assertOk();
    expect(app()->getLocale())->toBe('bn');
});

it('shares translations and locale with Inertia', function (): void {
    $user = User::factory()->role('owner')->create();

    $response = $this->actingAs($user, 'staff')->get('/admin');

    $props = $response->viewData('page')['props'];

    expect($props['locale'])->toBe('en')
        ->and($props['translations'])->toHaveKey('nav.dashboard')
        ->and($props['translations']['nav.dashboard'])->toBe('Dashboard');
});
