<?php

declare(strict_types=1);

use App\Models\Branch;
use App\Models\Ingredient;
use App\Models\RestaurantTable;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function (): void {
    $this->seed([RoleSeeder::class]);

    $this->branch = Branch::factory()->create(['code' => 'BR01']);
    RestaurantTable::factory()->create(['branch_id' => $this->branch->id, 'name' => 'T1']);
});

it('gives a cashier tables and the open shift but no inventory lists', function (): void {
    $cashier = User::factory()->role('cashier', $this->branch->id)->create();
    $token = $cashier->createToken('staff', ['staff'])->plainTextToken;

    $response = $this->withToken($token)
        ->getJson('/api/v1/branches/'.$this->branch->id.'/reference')
        ->assertOk();

    expect($response->json('data.tables'))->toHaveCount(1)
        ->and($response->json('data'))->toHaveKey('current_shift')
        ->and($response->json('data'))->not->toHaveKey('ingredients')
        ->and($response->json('data'))->not->toHaveKey('riders');
});

it('gives an owner the inventory and dispatch lookup lists', function (): void {
    $unit = Unit::query()->firstOrCreate(
        ['symbol' => 'kg'],
        ['name' => 'Kilogram', 'name_bn' => 'kg', 'type' => 'weight', 'factor' => 1],
    );
    Ingredient::query()->create(['name' => 'Rice', 'unit_id' => $unit->id, 'reorder_level' => 5, 'is_active' => true]);

    $owner = User::factory()->role('owner')->create();
    $token = $owner->createToken('staff', ['staff'])->plainTextToken;

    $data = $this->withToken($token)
        ->getJson('/api/v1/branches/'.$this->branch->id.'/reference')
        ->assertOk()
        ->json('data');

    expect($data)->toHaveKeys(['units', 'ingredients', 'suppliers', 'riders', 'tables'])
        ->and($data['ingredients'][0]['unit_symbol'])->toBe('kg');
});

it('hides a branch the user cannot access', function (): void {
    $other = Branch::factory()->create(['code' => 'BR02']);
    $cashier = User::factory()->role('cashier', $this->branch->id)->create();
    $token = $cashier->createToken('staff', ['staff'])->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v1/branches/'.$other->id.'/reference')
        ->assertNotFound();
});
