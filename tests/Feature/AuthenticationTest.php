<?php

declare(strict_types=1);

use App\Models\Branch;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
});

it('logs in with a username', function (): void {
    $user = User::factory()->create([
        'username' => 'cashier01',
        'password' => 'Secret123',
    ]);

    $response = $this->post('/login', [
        'login' => 'cashier01',
        'password' => 'Secret123',
    ]);

    $response->assertRedirect('/admin');
    expect(auth('staff')->user()?->id)->toBe($user->id);
});

it('logs in with an email', function (): void {
    $user = User::factory()->create([
        'email' => 'staff@restaurant.com',
        'password' => 'Secret123',
    ]);

    $this->post('/login', [
        'login' => 'staff@restaurant.com',
        'password' => 'Secret123',
    ]);

    expect(auth('staff')->user()?->id)->toBe($user->id);
});

it('logs in with a mobile number in any common format', function (string $login): void {
    $user = User::factory()->create([
        'mobile' => '8801711000001',
        'password' => 'Secret123',
    ]);

    $this->post('/login', [
        'login' => $login,
        'password' => 'Secret123',
    ]);

    expect(auth('staff')->user()?->id)->toBe($user->id);
})->with([
    'local' => ['01711000001'],
    'country code' => ['+8801711000001'],
    'bare country code' => ['8801711000001'],
]);

it('rejects a wrong password', function (): void {
    User::factory()->create(['username' => 'cashier01', 'password' => 'Secret123']);

    $response = $this->from('/login')->post('/login', [
        'login' => 'cashier01',
        'password' => 'WrongPassword',
    ]);

    $response->assertSessionHasErrors('login');
    expect(auth('staff')->user())->toBeNull();
});

it('rejects an inactive user', function (): void {
    User::factory()->create([
        'username' => 'gone',
        'password' => 'Secret123',
        'is_active' => false,
    ]);

    $this->post('/login', [
        'login' => 'gone',
        'password' => 'Secret123',
    ]);

    expect(auth('staff')->user())->toBeNull();
});

it('rate limits login after five failed attempts', function (): void {
    User::factory()->create(['username' => 'cashier01', 'password' => 'Secret123']);

    foreach (range(1, 5) as $attempt) {
        $this->post('/login', [
            'login' => 'cashier01',
            'password' => 'WrongPassword',
        ]);
    }

    $response = $this->post('/login', [
        'login' => 'cashier01',
        'password' => 'Secret123',
    ]);

    $response->assertSessionHasErrors('login');
    expect(auth('staff')->user())->toBeNull();
});

it('logs the user out and regenerates the session', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user, 'staff');

    $response = $this->post('/logout');

    $response->assertRedirect('/login');
    expect(auth('staff')->user())->toBeNull();
});

it('blocks the admin panel for guests', function (): void {
    $response = $this->get('/admin');

    $response->assertRedirect('/login');
});

it('allows the admin panel for a staff member with access permission', function (): void {
    $user = User::factory()->role('cashier')->create();

    $response = $this->actingAs($user, 'staff')->get('/admin');

    $response->assertOk();
});

it('shows only the branches the user can access in shared props', function (): void {
    $branchA = Branch::factory()->create(['code' => 'BR01']);
    Branch::factory()->create(['code' => 'BR02']);

    $scoped = User::factory()->role('cashier', $branchA->id)->create();
    $global = User::factory()->role('owner')->create();

    $scopedResponse = $this->actingAs($scoped, 'staff')->get('/admin');
    $globalResponse = $this->actingAs($global, 'staff')->get('/admin');

    expect(count($scopedResponse->viewData('page')['props']['branches']))->toBe(1);
    expect(count($globalResponse->viewData('page')['props']['branches']))->toBe(2);
});
