<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
});

it('issues a sanctum token for valid api credentials', function (): void {
    $user = User::factory()->create([
        'username' => 'cashier01',
        'password' => 'Secret123',
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'login' => 'cashier01',
        'password' => 'Secret123',
    ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['data' => ['token', 'user' => ['id', 'name', 'roles', 'permissions']]]);

    expect($response->json('data.user.id'))->toBe($user->id);
});

it('rejects invalid api credentials with the error envelope', function (): void {
    User::factory()->create(['username' => 'cashier01', 'password' => 'Secret123']);

    $response = $this->postJson('/api/v1/auth/login', [
        'login' => 'cashier01',
        'password' => 'nope',
    ]);

    $response->assertStatus(401)
        ->assertJsonPath('success', false)
        ->assertJsonPath('error.code', 'UNAUTHENTICATED');
});

it('returns the current user with roles for a valid token', function (): void {
    $user = User::factory()->role('owner')->create(['username' => 'theowner', 'password' => 'Secret123']);

    $login = $this->postJson('/api/v1/auth/login', [
        'login' => 'theowner',
        'password' => 'Secret123',
    ]);

    $token = $login->json('data.token');

    $response = $this->withToken($token)->getJson('/api/v1/auth/me');

    $response->assertOk()
        ->assertJsonPath('data.roles.0', 'owner');
});

it('revokes the token on logout', function (): void {
    $user = User::factory()->create(['username' => 'cashier01', 'password' => 'Secret123']);

    $token = $user->createToken('test', ['staff'])->plainTextToken;

    $response = $this->withToken($token)->postJson('/api/v1/auth/logout');

    $response->assertOk();
    expect($user->fresh()->tokens)->toBeEmpty();
});

it('rejects an unauthenticated request to protected endpoints', function (): void {
    $response = $this->getJson('/api/v1/auth/me');

    $response->assertStatus(401);
});
