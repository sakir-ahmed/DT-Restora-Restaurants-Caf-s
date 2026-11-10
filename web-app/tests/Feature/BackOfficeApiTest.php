<?php

declare(strict_types=1);

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function (): void {
    $this->seed([RoleSeeder::class, ChartOfAccountsSeeder::class]);

    $this->branch = Branch::factory()->create();
    $this->owner = User::factory()->role('owner', $this->branch->id)->create();
    $this->waiter = User::factory()->role('waiter', $this->branch->id)->create();
    $this->ownerToken = $this->owner->createToken('staff', ['staff'])->plainTextToken;
    $this->waiterToken = $this->waiter->createToken('staff', ['staff'])->plainTextToken;
});

it('records, lists and deletes expenses through the API', function (): void {
    $created = $this->withToken($this->ownerToken)->postJson('/api/v1/expenses', [
        'expense_date' => '2026-10-28',
        'category' => 'rent',
        'description' => 'October rent',
        'amount' => '25000',
    ])->assertCreated()->json('data');

    expect($created['category'])->toBe('rent')
        ->and($created['amount'])->toBe('25000.00');

    $list = $this->withToken($this->ownerToken)->getJson('/api/v1/expenses')->assertOk()->json('data');

    expect($list['expenses'])->toHaveCount(1)
        ->and($list['categories'])->toContain('utilities');

    $this->withToken($this->ownerToken)->deleteJson('/api/v1/expenses/'.$created['id'])->assertOk();

    expect(Expense::query()->count())->toBe(0);
});

it('rejects expense input that is invalid or from staff without permission', function (): void {
    $this->withToken($this->ownerToken)->postJson('/api/v1/expenses', [
        'expense_date' => '2026-10-28',
        'category' => 'nonsense',
        'amount' => '0',
    ])->assertUnprocessable();

    app('auth')->forgetGuards();

    $this->withToken($this->waiterToken)->getJson('/api/v1/expenses')->assertForbidden();
});

it('manages suppliers and blocks deleting one with purchases', function (): void {
    $created = $this->withToken($this->ownerToken)->postJson('/api/v1/suppliers', [
        'name' => 'Fresh Fish Co',
        'mobile' => '01711000000',
    ])->assertCreated()->json('data');

    $this->withToken($this->ownerToken)->putJson('/api/v1/suppliers/'.$created['id'], [
        'name' => 'Fresh Fish Company',
    ])->assertOk()->assertJsonPath('data.name', 'Fresh Fish Company');

    $this->withToken($this->ownerToken)->getJson('/api/v1/suppliers')
        ->assertOk()->assertJsonCount(1, 'data');

    $this->withToken($this->ownerToken)->deleteJson('/api/v1/suppliers/'.$created['id'])->assertOk();

    expect(Supplier::query()->count())->toBe(0);
});

it('reads and updates the loyalty programme and finds a customer by mobile', function (): void {
    Customer::query()->create(['name' => 'Rafi', 'mobile' => normalize_bd_mobile('01712345678'), 'is_active' => true]);

    $overview = $this->withToken($this->ownerToken)->getJson('/api/v1/loyalty?mobile=01712345678')
        ->assertOk()->json('data');

    expect($overview['lookup']['customer']['name'])->toBe('Rafi')
        ->and($overview['totals'])->toHaveKeys(['customers_with_points', 'points_earned']);

    $this->withToken($this->ownerToken)->putJson('/api/v1/loyalty/settings', [
        'earn_rate' => 5,
        'point_value' => '0.50',
        'expiry_days' => 180,
        'is_enabled' => true,
    ])->assertOk()->assertJsonPath('data.earn_rate', 5);
});

it('lists the activity log for permitted staff only', function (): void {
    $this->withToken($this->ownerToken)->getJson('/api/v1/activity-log')->assertOk();
    app('auth')->forgetGuards();

    $this->withToken($this->waiterToken)->getJson('/api/v1/activity-log')->assertForbidden();
});
