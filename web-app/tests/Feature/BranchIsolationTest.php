<?php

declare(strict_types=1);

use App\Models\Branch;
use App\Models\Employee;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\StockTransfer;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);

    $this->branchA = Branch::factory()->create(['code' => 'BR01']);
    $this->branchB = Branch::factory()->create(['code' => 'BR02']);
});

it('scopes menu data to the acting branch manager', function (): void {
    $manager = User::factory()->role('branch-manager', $this->branchA->id)->create();

    $categoryA = MenuCategory::factory()->create(['branch_id' => $this->branchA->id]);
    $categoryB = MenuCategory::factory()->create(['branch_id' => $this->branchB->id]);

    MenuItem::factory()->create(['branch_id' => $this->branchA->id, 'menu_category_id' => $categoryA->id]);
    MenuItem::factory()->create(['branch_id' => $this->branchB->id, 'menu_category_id' => $categoryB->id]);

    $response = $this->actingAs($manager, 'staff')->get('/admin/menu');

    $response->assertOk();

    $categories = $response->viewData('page')['props']['categories'];

    expect(count($categories))->toBe(1)
        ->and($categories[0]['id'])->toBe($categoryA->id);
});

it('lets the owner see every branch menu', function (): void {
    $owner = User::factory()->role('owner')->create();

    $categoryA = MenuCategory::factory()->create(['branch_id' => $this->branchA->id]);
    $categoryB = MenuCategory::factory()->create(['branch_id' => $this->branchB->id]);

    $this->actingAs($owner, 'staff')->get('/admin/menu');

    // Owner page shows the session-selected branch only (switcher), but the
    // model queries themselves must not be restricted.
    expect(MenuCategory::count())->toBe(2);
});

it('returns 404 when a manager touches another branch item', function (string $method, string $routeName): void {
    $manager = User::factory()->role('branch-manager', $this->branchA->id)->create();

    $categoryB = MenuCategory::factory()->create(['branch_id' => $this->branchB->id]);
    $itemB = MenuItem::factory()->create(['branch_id' => $this->branchB->id, 'menu_category_id' => $categoryB->id]);

    $response = $this->actingAs($manager, 'staff')->{$method}(route($routeName, $itemB->id), [
        'menu_category_id' => $categoryB->id,
        'name' => 'Hacked',
        'base_price' => '1.00',
    ]);

    $response->assertNotFound();
})->with([
    'update' => ['put', 'admin.menu.items.update'],
    'delete' => ['delete', 'admin.menu.items.destroy'],
]);

it('returns 404 when a manager edits another branch employee', function (): void {
    $manager = User::factory()->role('branch-manager', $this->branchA->id)->create();

    $employeeB = Employee::factory()->create(['branch_id' => $this->branchB->id]);

    $response = $this->actingAs($manager, 'staff')->put(route('admin.staff.employees.update', $employeeB->id), [
        'name' => $employeeB->name,
        'designation' => 'Chef',
        'monthly_salary' => '100.00',
    ]);

    $response->assertNotFound();
});

it('blocks branch switching to a branch the user cannot access', function (): void {
    $manager = User::factory()->role('branch-manager', $this->branchA->id)->create();

    $response = $this->actingAs($manager, 'staff')->post('/admin/branch-switch', [
        'branch_id' => $this->branchB->id,
    ]);

    $response->assertNotFound();
    expect(session('branch_id'))->toBeNull();
});

it('filters the branch list for branch managers', function (): void {
    $manager = User::factory()->role('branch-manager', $this->branchA->id)->create();

    $response = $this->actingAs($manager, 'staff')->get('/admin/branches');

    $branches = $response->viewData('page')['props']['branches'];

    expect(count($branches))->toBe(1)
        ->and($branches[0]['id'])->toBe($this->branchA->id);
});

it('hides stock transfers between other branches from a branch manager', function (): void {
    $other = Branch::factory()->create(['code' => 'BR03']);
    $manager = User::factory()->role('branch-manager', $this->branchA->id)->create();

    $transfer = StockTransfer::query()->create([
        'from_branch_id' => $this->branchB->id,
        'to_branch_id' => $other->id,
        'status' => StockTransfer::STATUS_REQUESTED,
    ]);

    $this->actingAs($manager, 'staff')
        ->post(route('admin.stock-transfers.approve', $transfer))
        ->assertNotFound();

    $this->actingAs($manager, 'staff')
        ->post(route('admin.stock-transfers.receive', $transfer))
        ->assertNotFound();
});
