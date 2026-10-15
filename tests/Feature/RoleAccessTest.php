<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
});

it('gives every product role the admin access permission', function (string $role): void {
    $user = User::factory()->role($role)->create();

    expect($user->can('admin.access'))->toBeTrue();
})->with([
    'owner',
    'branch-manager',
    'cashier',
    'waiter',
    'kitchen-manager',
    'accountant',
    'rider',
]);

it('gives the super-admin role every permission through the gate', function (): void {
    $user = User::factory()->role('super-admin')->create();

    expect($user->can('settings.manage'))->toBeTrue()
        ->and($user->can('journals.post'))->toBeTrue()
        ->and($user->can('some-future-permission'))->toBeTrue();
});

it('scopes role permissions to the seeded matrix', function (string $role, string $permission, bool $allowed): void {
    $user = User::factory()->role($role)->create();

    expect($user->can($permission))->toBe($allowed);
})->with([
    'cashier can open POS' => ['cashier', 'pos.access', true],
    'waiter cannot open POS' => ['waiter', 'pos.access', false],
    'cashier cannot void orders' => ['cashier', 'orders.void', false],
    'branch-manager can void orders' => ['branch-manager', 'orders.void', true],
    'kitchen-manager can log wastage' => ['kitchen-manager', 'wastage.log', true],
    'cashier cannot post journals' => ['cashier', 'journals.post', false],
    'accountant can post journals' => ['accountant', 'journals.post', true],
    'rider cannot manage menu' => ['rider', 'menu.manage', false],
    'owner can manage branches' => ['owner', 'branches.manage', true],
]);

it('returns 403 for a user with no role on the admin panel', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'staff')->get('/admin');

    $response->assertForbidden();
});

it('lets a cashier open the ui-kit showcase', function (): void {
    $cashier = User::factory()->role('cashier')->create();

    $response = $this->actingAs($cashier, 'staff')->get('/admin/ui-kit');

    $response->assertOk();
});
