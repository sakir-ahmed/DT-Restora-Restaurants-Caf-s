<?php

declare(strict_types=1);

use App\Models\Branch;
use App\Models\Employee;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);

    $this->branch = Branch::factory()->create(['code' => 'BR01']);
    $this->manager = User::factory()->role('branch-manager', $this->branch->id)->create();
});

it('creates an employee with a salary in the acting branch', function (): void {
    $response = $this->actingAs($this->manager, 'staff')->post('/admin/staff/employees', [
        'name' => 'Mizanur Rahman',
        'designation' => 'Head Chef',
        'mobile' => '01711000099',
        'monthly_salary' => '45000.00',
        'joined_at' => '2026-01-05',
        'is_active' => true,
    ]);

    $response->assertRedirect();

    $employee = Employee::query()->where('name', 'Mizanur Rahman')->firstOrFail();

    expect($employee->branch_id)->toBe($this->branch->id)
        ->and($employee->monthly_salary)->toBe('45000.00')
        ->and($employee->designation)->toBe('Head Chef');
});

it('lists employees of the acting branch only', function (): void {
    $otherBranch = Branch::factory()->create(['code' => 'BR02']);

    Employee::factory()->count(2)->create(['branch_id' => $this->branch->id]);
    Employee::factory()->count(3)->create(['branch_id' => $otherBranch->id]);

    $response = $this->actingAs($this->manager, 'staff')->get('/admin/staff');

    $employees = $response->viewData('page')['props']['employees'];

    expect(count($employees))->toBe(2);
});

it('deactivates instead of deleting an employee', function (): void {
    $employee = Employee::factory()->create(['branch_id' => $this->branch->id]);

    $response = $this->actingAs($this->manager, 'staff')->delete("/admin/staff/employees/{$employee->id}");

    $response->assertRedirect();

    expect($employee->fresh()->exists)->toBeTrue()
        ->and($employee->fresh()->is_active)->toBeFalse();
});

it('rejects employee writes from roles without permission', function (): void {
    $cashier = User::factory()->role('cashier', $this->branch->id)->create();

    $response = $this->actingAs($cashier, 'staff')->post('/admin/staff/employees', [
        'name' => 'Nope',
        'designation' => 'Chef',
        'monthly_salary' => '1000.00',
    ]);

    $response->assertForbidden();
});

it('creates a staff login with a role', function (): void {
    $owner = User::factory()->role('owner')->create();

    $response = $this->actingAs($owner, 'staff')->post('/admin/staff/users', [
        'name' => 'New Cashier',
        'username' => 'newcashier',
        'email' => 'newcashier@restaurant.com',
        'mobile' => '01712000099',
        'password' => 'Secret12345',
        'roles' => ['cashier'],
        'branch_id' => $this->branch->id,
        'is_active' => true,
    ]);

    $response->assertRedirect();

    $user = User::query()->where('username', 'newcashier')->firstOrFail();

    expect($user->branch_id)->toBe($this->branch->id)
        ->and($user->hasRole('cashier'))->toBeTrue()
        ->and($user->mobile)->toBe('8801712000099');
});

it('keeps owner class accounts unscoped to a branch', function (): void {
    $owner = User::factory()->role('owner')->create();

    $response = $this->actingAs($owner, 'staff')->post('/admin/staff/users', [
        'name' => 'Second Owner',
        'username' => 'owner2',
        'password' => 'Secret12345',
        'roles' => ['owner'],
        'is_active' => true,
    ]);

    $response->assertRedirect();

    expect(User::query()->where('username', 'owner2')->first()->branch_id)->toBeNull();
});

it('blocks user management without the staff.manage permission', function (): void {
    $response = $this->actingAs($this->manager, 'staff')->post('/admin/staff/users', [
        'name' => 'Nope',
        'password' => 'Secret12345',
        'roles' => ['cashier'],
    ]);

    $response->assertForbidden();
});
