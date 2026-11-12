<?php

declare(strict_types=1);

use App\Models\Branch;
use App\Models\Ingredient;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Recipe;
use App\Models\RestaurantTable;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UnitSeeder;

beforeEach(function (): void {
    $this->seed([RoleSeeder::class, UnitSeeder::class]);

    $this->branch = Branch::factory()->create(['code' => 'BR01']);
    $this->owner = User::factory()->role('owner')->create();
    $this->token = $this->owner->createToken('staff', ['staff'])->plainTextToken;
    $this->base = '/api/v1/branches/'.$this->branch->id;
});

it('creates a custom unit as a multiple of a standard one, edits it and deletes it', function (): void {
    $pcs = Unit::query()->where('symbol', 'pcs')->firstOrFail();

    $created = $this->withToken($this->token)->postJson('/api/v1/units', [
        'name' => 'Box', 'symbol' => 'box', 'type' => 'count', 'qty' => 12, 'reference_unit_id' => $pcs->id,
    ])->assertCreated()->assertJsonPath('data.factor', '12.000000')->assertJsonPath('data.is_system', false);

    $id = $created->json('data.id');

    $this->withToken($this->token)->putJson('/api/v1/units/'.$id, [
        'name' => 'Big box', 'symbol' => 'box', 'qty' => 24, 'reference_unit_id' => $pcs->id,
    ])->assertOk()->assertJsonPath('data.factor', '24.000000');

    $this->withToken($this->token)->deleteJson('/api/v1/units/'.$id)->assertOk();

    expect(Unit::query()->whereKey($id)->exists())->toBeFalse();
});

it('keeps standard units fixed and a unit in use undeletable', function (): void {
    $kg = Unit::query()->where('symbol', 'kg')->firstOrFail();
    $this->withToken($this->token)->deleteJson('/api/v1/units/'.$kg->id)->assertForbidden();

    $pcs = Unit::query()->where('symbol', 'pcs')->firstOrFail();
    $box = $this->withToken($this->token)->postJson('/api/v1/units', [
        'name' => 'Box', 'symbol' => 'box', 'type' => 'count', 'qty' => 12, 'reference_unit_id' => $pcs->id,
    ])->json('data.id');
    Ingredient::query()->create(['name' => 'Eggs', 'unit_id' => $box, 'reorder_level' => 1, 'is_active' => true]);

    $this->withToken($this->token)->deleteJson('/api/v1/units/'.$box)->assertUnprocessable();
});

it('refuses a reference unit of another type', function (): void {
    $kg = Unit::query()->where('symbol', 'kg')->firstOrFail();

    $this->withToken($this->token)->postJson('/api/v1/units', [
        'name' => 'Box', 'symbol' => 'box', 'type' => 'count', 'qty' => 2, 'reference_unit_id' => $kg->id,
    ])->assertUnprocessable();
});

it('keeps unit management to people with settings access', function (): void {
    $cashier = User::factory()->role('cashier', $this->branch->id)->create();
    $token = $cashier->createToken('staff', ['staff'])->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/units')->assertForbidden();
});

it('creates, edits, lists and removes a table', function (): void {
    $id = $this->withToken($this->token)->postJson($this->base.'/tables', ['name' => 'T9', 'seats' => 4, 'area' => 'Hall'])
        ->assertCreated()->json('data.id');

    $this->withToken($this->token)->postJson($this->base.'/tables', ['name' => 'T9', 'seats' => 2])->assertUnprocessable();

    $this->withToken($this->token)->putJson($this->base.'/tables/'.$id, ['name' => 'T10', 'seats' => 6])
        ->assertOk()->assertJsonPath('data.seats', 6);

    expect($this->withToken($this->token)->getJson($this->base.'/tables')->json('data.tables'))->toHaveCount(1);

    $before = RestaurantTable::query()->find($id)->qr_token;
    $this->withToken($this->token)->postJson($this->base.'/tables/'.$id.'/qr')->assertOk();
    expect(RestaurantTable::query()->find($id)->qr_token)->not->toBe($before);

    $this->withToken($this->token)->deleteJson($this->base.'/tables/'.$id)->assertOk();
    expect(RestaurantTable::query()->count())->toBe(0);
});

it('does not let a waiter manage tables', function (): void {
    $waiter = User::factory()->role('waiter', $this->branch->id)->create();
    $token = $waiter->createToken('staff', ['staff'])->plainTextToken;

    $this->withToken($token)->postJson($this->base.'/tables', ['name' => 'X', 'seats' => 2])->assertForbidden();
});

it('keeps the unit of a recipe line and stores the quantity in base units', function (): void {
    $ml = Unit::query()->where('symbol', 'ml')->firstOrFail();
    $l = Unit::query()->where('symbol', 'l')->firstOrFail();
    $milk = Ingredient::query()->create(['name' => 'Milk', 'unit_id' => $ml->id, 'reorder_level' => 1, 'is_active' => true]);
    $category = MenuCategory::factory()->create(['branch_id' => $this->branch->id]);
    $item = MenuItem::factory()->create(['branch_id' => $this->branch->id, 'menu_category_id' => $category->id]);

    $this->withToken($this->token)->postJson('/api/v1/recipes', [
        'menu_item_id' => $item->id,
        'lines' => [['ingredient_id' => $milk->id, 'qty' => 250, 'unit_id' => $ml->id]],
    ])->assertCreated();

    $stored = (float) Recipe::query()->where('menu_item_id', $item->id)->first()->items()->first()->qty;

    $this->withToken($this->token)->postJson('/api/v1/recipes', [
        'menu_item_id' => $item->id,
        'lines' => [['ingredient_id' => $milk->id, 'qty' => 1, 'unit_id' => $l->id]],
    ])->assertCreated();

    $storedLitre = (float) Recipe::query()->where('menu_item_id', $item->id)->first()->items()->first()->qty;

    expect($storedLitre)->toBe($stored * 4);
});
