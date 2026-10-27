<?php

declare(strict_types=1);

use App\Models\Branch;
use App\Models\MenuItem;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\RootAdminSeeder;
use Illuminate\Support\Facades\Hash;

it('seeds only bootstrap data with a random admin password when demo mode is off', function (): void {
    config(['app.is_demo' => false]);

    $this->artisan('db:seed', ['--class' => DatabaseSeeder::class])
        ->expectsOutputToContain('Root administrator created')
        ->assertSuccessful();

    $admin = User::query()->where('username', 'admin')->firstOrFail();

    expect($admin->hasRole('super-admin'))->toBeTrue()
        ->and($admin->branch_id)->toBeNull()
        ->and(Branch::query()->count())->toBe(0)
        ->and(MenuItem::query()->withoutGlobalScopes()->count())->toBe(0)
        ->and(Setting::query()->count())->toBe(1)
        ->and(User::query()->count())->toBe(1);
});

it('seeds the sample branches only in demo mode', function (): void {
    config(['app.is_demo' => true]);

    $this->seed(DatabaseSeeder::class);

    expect(Branch::query()->count())->toBe(3);
});

it('never resets an existing admin password when seeding again', function (): void {
    $this->seed(RoleSeeder::class);

    $admin = User::factory()->create(['username' => RootAdminSeeder::USERNAME, 'password' => 'KnownPassword-123']);

    $this->seed(RootAdminSeeder::class);

    expect(Hash::check('KnownPassword-123', $admin->fresh()->password))->toBeTrue();
});

it('resets a staff password to a new random strong one', function (): void {
    $admin = User::factory()->create(['username' => 'admin', 'password' => 'OldPassword-123']);

    $this->artisan('admin:reset-password')->assertSuccessful();

    expect(Hash::check('OldPassword-123', $admin->fresh()->password))->toBeFalse();

    $this->artisan('admin:reset-password', ['username' => 'nobody'])->assertFailed();
});

it('shows the Google button on customer login only when Google is configured', function (): void {
    config(['services.google.client_id' => null, 'services.google.client_secret' => null]);

    $this->get('/customer/login')->assertInertia(fn ($page) => $page->where('googleEnabled', false));

    config(['services.google.client_id' => 'id', 'services.google.client_secret' => 'secret']);

    $this->get('/customer/login')->assertInertia(fn ($page) => $page->where('googleEnabled', true));
});

it('has no staff Google sign-in', function (): void {
    $this->get('/auth/google/redirect')->assertNotFound();
});

it('lets the fresh production admin open the back office before any branch exists', function (): void {
    config(['app.is_demo' => false]);
    $this->seed(DatabaseSeeder::class);

    $admin = User::query()->where('username', 'admin')->firstOrFail();

    foreach (['/admin', '/admin/branches', '/admin/settings', '/admin/menu', '/admin/orders', '/admin/pos', '/admin/tables', '/admin/reservations', '/admin/users'] as $path) {
        $status = $this->actingAs($admin, 'staff')->get($path)->getStatusCode();

        expect($status)->toBeLessThan(500, "{$path} returned {$status}");
    }
});
