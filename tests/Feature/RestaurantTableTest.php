<?php

declare(strict_types=1);

use App\Models\Branch;
use App\Models\DataVersion;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);

    $this->branch = Branch::factory()->create(['code' => 'BR01']);
    $this->manager = User::factory()->role('branch-manager', $this->branch->id)->create();
});

it('lists only the acting branch tables and their branch qr link', function (): void {
    $own = RestaurantTable::factory()->create([
        'branch_id' => $this->branch->id,
        'name' => 'T1',
        'area' => 'Indoor',
    ]);
    $otherBranch = Branch::factory()->create(['code' => 'BR02']);
    RestaurantTable::factory()->create(['branch_id' => $otherBranch->id, 'name' => 'T9']);

    $response = $this->actingAs($this->manager, 'staff')->get('/admin/tables');

    $response->assertOk();

    $tables = collect($response->viewData('page')['props']['tables']);

    expect($tables->pluck('id')->all())->toBe([$own->id])
        ->and($tables->first()['qr_url'])->toBe(route('web.qr.branch', [
            'branchCode' => 'br01',
            'table' => 'T1',
        ]));
});

it('lists tables by area and then by natural table number', function (): void {
    RestaurantTable::factory()->create(['branch_id' => $this->branch->id, 'name' => 'T10', 'area' => 'Ground']);
    RestaurantTable::factory()->create(['branch_id' => $this->branch->id, 'name' => 'T2', 'area' => 'Ground']);
    RestaurantTable::factory()->create(['branch_id' => $this->branch->id, 'name' => 'F1', 'area' => 'Family']);

    $response = $this->actingAs($this->manager, 'staff')->get('/admin/tables');

    $response->assertOk();

    expect(collect($response->viewData('page')['props']['tables'])->pluck('name')->all())
        ->toBe(['F1', 'T2', 'T10']);
});

it('shows an owner only the branch selected in the session', function (): void {
    $owner = User::factory()->role('owner')->create();
    $other = Branch::factory()->create(['code' => 'BR02']);
    RestaurantTable::factory()->create(['branch_id' => $this->branch->id, 'name' => 'A1']);
    RestaurantTable::factory()->create(['branch_id' => $other->id, 'name' => 'B1']);

    $response = $this->actingAs($owner, 'staff')
        ->withSession(['branch_id' => $other->id])
        ->get('/admin/tables');

    $response->assertOk();

    expect(collect($response->viewData('page')['props']['tables'])->pluck('name')->all())->toBe(['B1']);
});

it('creates a table on the manager branch and ignores a posted branch id', function (): void {
    $other = Branch::factory()->create(['code' => 'BR02']);
    $before = DataVersion::current();

    $response = $this->actingAs($this->manager, 'staff')->post('/admin/tables', [
        'name' => 'Window 1',
        'area' => 'Garden',
        'seats' => 2,
        'is_active' => true,
        'branch_id' => $other->id,
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('restaurant_tables', [
        'branch_id' => $this->branch->id,
        'name' => 'Window 1',
        'area' => 'Garden',
        'seats' => 2,
        'status' => 'free',
    ]);
    $this->assertDatabaseMissing('restaurant_tables', [
        'branch_id' => $other->id,
        'name' => 'Window 1',
    ]);
    expect(DataVersion::current())->toBe($before + 1);
});

it('rejects a duplicate table name in the same branch', function (): void {
    RestaurantTable::factory()->create(['branch_id' => $this->branch->id, 'name' => 'T1']);

    $response = $this->actingAs($this->manager, 'staff')->post('/admin/tables', [
        'name' => 'T1',
        'seats' => 4,
        'is_active' => true,
    ]);

    $response->assertSessionHasErrors('name');
});

it('allows the same table name in another branch', function (): void {
    $other = Branch::factory()->create(['code' => 'BR02']);
    RestaurantTable::factory()->create(['branch_id' => $other->id, 'name' => 'T1']);

    $response = $this->actingAs($this->manager, 'staff')->post('/admin/tables', [
        'name' => 'T1',
        'seats' => 4,
        'is_active' => true,
    ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    $this->assertDatabaseHas('restaurant_tables', [
        'branch_id' => $this->branch->id,
        'name' => 'T1',
    ]);
});

it('creates a numbered range of tables', function (): void {
    $response = $this->actingAs($this->manager, 'staff')->post('/admin/tables', [
        'is_range' => true,
        'prefix' => 'G',
        'from' => 1,
        'to' => 3,
        'area' => 'Garden',
        'seats' => 6,
        'is_active' => true,
    ]);

    $response->assertRedirect();

    expect(RestaurantTable::withoutBranchScope()->where('branch_id', $this->branch->id)->orderBy('name')->pluck('name')->all())
        ->toBe(['G1', 'G2', 'G3']);
});

it('rejects a range that reuses an existing name', function (): void {
    RestaurantTable::factory()->create(['branch_id' => $this->branch->id, 'name' => 'G2']);

    $response = $this->actingAs($this->manager, 'staff')->post('/admin/tables', [
        'is_range' => true,
        'prefix' => 'G',
        'from' => 1,
        'to' => 3,
        'seats' => 4,
        'is_active' => true,
    ]);

    $response->assertSessionHasErrors([
        'prefix' => __('tables.range_conflict', ['names' => 'G2']),
    ]);
});

it('rejects a range larger than 50 tables', function (): void {
    $response = $this->actingAs($this->manager, 'staff')->post('/admin/tables', [
        'is_range' => true,
        'prefix' => 'T',
        'from' => 1,
        'to' => 51,
        'seats' => 4,
        'is_active' => true,
    ]);

    $response->assertSessionHasErrors([
        'to' => __('tables.range_too_large'),
    ]);
});

it('updates seats area and reserved status', function (): void {
    $table = RestaurantTable::factory()->create([
        'branch_id' => $this->branch->id,
        'name' => 'T4',
        'seats' => 4,
        'status' => 'free',
    ]);

    $response = $this->actingAs($this->manager, 'staff')->put(route('admin.tables.update', $table), [
        'name' => 'T4',
        'area' => 'Family',
        'seats' => 8,
        'status' => 'reserved',
        'is_active' => true,
    ]);

    $response->assertRedirect();

    $table->refresh();

    expect($table->area)->toBe('Family')
        ->and($table->seats)->toBe(8)
        ->and($table->status)->toBe('reserved');
});

it('keeps an occupied table occupied when a status change is posted', function (): void {
    $table = RestaurantTable::factory()->occupied()->create([
        'branch_id' => $this->branch->id,
        'name' => 'T5',
    ]);

    $response = $this->actingAs($this->manager, 'staff')->put(route('admin.tables.update', $table), [
        'name' => 'T5',
        'seats' => 4,
        'status' => 'free',
        'is_active' => true,
    ]);

    $response->assertSessionHasErrors([
        'status' => __('tables.occupied_locked'),
    ]);

    expect($table->refresh()->status)->toBe('occupied');
});

it('removes a table that has no orders', function (): void {
    $table = RestaurantTable::factory()->create([
        'branch_id' => $this->branch->id,
        'name' => 'T6',
    ]);

    $response = $this->actingAs($this->manager, 'staff')->delete(route('admin.tables.destroy', $table));

    $response->assertRedirect();

    expect(RestaurantTable::withoutBranchScope()->find($table->id))->toBeNull();
});

it('turns off a table that already has orders', function (): void {
    $table = RestaurantTable::factory()->create([
        'branch_id' => $this->branch->id,
        'name' => 'T7',
    ]);

    Order::query()->create([
        'branch_id' => $this->branch->id,
        'invoice_no' => 'BR01-TBL-0001',
        'order_type' => 'dine_in',
        'restaurant_table_id' => $table->id,
        'status' => 'paid',
        'subtotal' => '100.00',
        'total' => '100.00',
    ]);

    $response = $this->actingAs($this->manager, 'staff')->delete(route('admin.tables.destroy', $table));

    $response->assertRedirect();

    expect($table->refresh()->is_active)->toBeFalse()
        ->and(RestaurantTable::withoutBranchScope()->find($table->id))->not->toBeNull();
});

it('returns 404 when a manager edits another branch table', function (): void {
    $other = Branch::factory()->create(['code' => 'BR02']);
    $table = RestaurantTable::factory()->create(['branch_id' => $other->id, 'name' => 'X1']);

    $response = $this->actingAs($this->manager, 'staff')->put(route('admin.tables.update', $table), [
        'name' => 'Hacked',
        'seats' => 1,
        'is_active' => true,
    ]);

    $response->assertNotFound();
    expect(RestaurantTable::withoutBranchScope()->find($table->id)?->name)->toBe('X1');
});

it('forbids a waiter from the tables page', function (): void {
    $waiter = User::factory()->role('waiter', $this->branch->id)->create();

    $this->actingAs($waiter, 'staff')->get('/admin/tables')->assertForbidden();
});

it('redirects a guest away from the tables page', function (): void {
    $this->get('/admin/tables')->assertRedirect(route('login'));
});

it('sends a branch qr code to that branch menu and hides an inactive table', function (): void {
    RestaurantTable::factory()->create([
        'branch_id' => $this->branch->id,
        'name' => 'T1',
    ]);
    RestaurantTable::factory()->inactive()->create([
        'branch_id' => $this->branch->id,
        'name' => 'T2',
    ]);

    $this->get('/q/br01/T1')->assertRedirect(route('web.menu', [
        'branchCode' => 'br01',
        'table' => 'T1',
    ]));

    $this->get('/menu/br01?table=T1')->assertOk();
    $this->get('/menu/br01?table=T2')->assertNotFound();
});
