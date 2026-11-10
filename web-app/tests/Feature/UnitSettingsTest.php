<?php

declare(strict_types=1);

use App\Models\Ingredient;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UnitSeeder;

beforeEach(function (): void {
    $this->seed([RoleSeeder::class, UnitSeeder::class]);
    $this->owner = User::factory()->role('owner')->create();
    $this->pcs = Unit::query()->where('symbol', 'pcs')->first();
});

it('marks the standard units as fixed and lists them in Settings', function (): void {
    expect(Unit::query()->where('is_system', true)->count())->toBe(10);

    $this->actingAs($this->owner, 'staff')->get(route('admin.settings.index'))
        ->assertInertia(fn ($page) => $page->has('unitList', 10)->where('unitList.0.is_system', true));
});

it('adds a box that holds 12 pieces and a large box that holds 2 boxes', function (): void {
    $this->actingAs($this->owner, 'staff')
        ->post(route('admin.settings.units.store'), ['name' => 'Box', 'symbol' => 'box', 'type' => 'count', 'qty' => '12', 'reference_unit_id' => $this->pcs->id])
        ->assertSessionHasNoErrors();

    $box = Unit::query()->where('symbol', 'box')->first();

    expect((float) $box->factor)->toBe(12.0)->and($box->is_system)->toBeFalse()->and($box->base_unit_id)->toBe($this->pcs->id);

    $this->actingAs($this->owner, 'staff')
        ->post(route('admin.settings.units.store'), ['name' => 'Large box', 'name_bn' => 'বড় বক্স', 'symbol' => 'lbox', 'type' => 'count', 'qty' => '2', 'reference_unit_id' => $box->id])
        ->assertSessionHasNoErrors();

    expect((float) Unit::query()->where('symbol', 'lbox')->value('factor'))->toBe(24.0)
        ->and($box->fresh()->toBase(3))->toBe(36.0);
});

it('rejects a duplicate symbol, a reference of another kind and a nonsense size', function (): void {
    $kg = Unit::query()->where('symbol', 'kg')->first();

    $post = fn (array $data) => $this->actingAs($this->owner, 'staff')->post(route('admin.settings.units.store'), $data + ['name' => 'X', 'symbol' => 'xx', 'type' => 'count', 'qty' => '1', 'reference_unit_id' => $this->pcs->id]);

    $post(['symbol' => 'kg'])->assertSessionHasErrors('symbol');
    $post(['reference_unit_id' => $kg->id])->assertSessionHasErrors('reference_unit_id');
    $post(['qty' => '0'])->assertSessionHasErrors('qty');
    $post(['symbol' => '<b>'])->assertSessionHasErrors('symbol');
});

it('never lets a standard unit be changed or deleted', function (): void {
    $kg = Unit::query()->where('symbol', 'kg')->first();

    $this->actingAs($this->owner, 'staff')->put(route('admin.settings.units.update', $kg), ['name' => 'Kilo', 'symbol' => 'kg', 'qty' => '1', 'reference_unit_id' => $this->pcs->id])->assertForbidden();
    $this->actingAs($this->owner, 'staff')->delete(route('admin.settings.units.destroy', $kg))->assertForbidden();

    expect((float) $kg->fresh()->factor)->toBe(1000.0);
});

it('locks the size of a unit that is already used but still lets it be renamed', function (): void {
    $box = Unit::query()->create(['name' => 'Box', 'symbol' => 'box', 'type' => 'count', 'base_unit_id' => $this->pcs->id, 'factor' => 12]);
    Ingredient::factory()->create(['unit_id' => $box->id]);

    $this->actingAs($this->owner, 'staff')
        ->put(route('admin.settings.units.update', $box), ['name' => 'Carton', 'symbol' => 'ctn', 'type' => 'weight', 'qty' => '99', 'reference_unit_id' => $this->pcs->id])
        ->assertSessionHasNoErrors();

    $box->refresh();

    expect($box->name)->toBe('Carton')->and($box->symbol)->toBe('ctn')->and((float) $box->factor)->toBe(12.0)->and($box->type)->toBe('count');

    $this->actingAs($this->owner, 'staff')->delete(route('admin.settings.units.destroy', $box))->assertSessionHas('error');

    expect(Unit::query()->whereKey($box->id)->exists())->toBeTrue();
});

it('needs the settings permission', function (): void {
    $cashier = User::factory()->role('cashier')->create();

    $this->actingAs($cashier, 'staff')->post(route('admin.settings.units.store'), ['name' => 'Box', 'symbol' => 'box', 'type' => 'count', 'qty' => '12', 'reference_unit_id' => $this->pcs->id])->assertForbidden();
});
