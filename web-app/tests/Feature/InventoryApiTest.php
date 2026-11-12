<?php

declare(strict_types=1);

use App\Models\Branch;
use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\IngredientStock;
use App\Models\Purchase;
use App\Models\StockCount;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UnitSeeder;

beforeEach(function (): void {
    $this->seed([RoleSeeder::class, ChartOfAccountsSeeder::class, UnitSeeder::class]);

    $this->branch = Branch::factory()->create(['code' => 'BR01']);
    $this->owner = User::factory()->role('owner')->create();
    $this->token = $this->owner->createToken('staff', ['staff'])->plainTextToken;
    $this->base = '/api/v1/branches/'.$this->branch->id;
    $this->kg = Unit::query()->where('symbol', 'kg')->firstOrFail();
    $this->rice = Ingredient::query()->create(['name' => 'Rice', 'unit_id' => $this->kg->id, 'reorder_level' => 1, 'is_active' => true]);
    $this->supplier = Supplier::query()->create(['name' => 'Agro', 'is_active' => true]);
});

function draftPurchase($test, float $qty = 10): int
{
    return $test->withToken($test->token)->postJson($test->base.'/purchases', [
        'supplier_id' => $test->supplier->id,
        'purchased_at' => now()->toDateString(),
        'items' => [['ingredient_id' => $test->rice->id, 'unit_id' => $test->kg->id, 'qty' => $qty, 'unit_cost' => 100]],
    ])->assertCreated()->json('data.id');
}

it('edits a draft purchase, receives it, corrects it and deletes it with a reason', function (): void {
    $id = draftPurchase($this);

    $this->withToken($this->token)->putJson($this->base.'/purchases/'.$id, [
        'supplier_id' => $this->supplier->id,
        'purchased_at' => now()->toDateString(),
        'items' => [['ingredient_id' => $this->rice->id, 'unit_id' => $this->kg->id, 'qty' => 20, 'unit_cost' => 90]],
    ])->assertOk()->assertJsonPath('data.items.0.qty', '20.000');

    $this->withToken($this->token)->postJson($this->base.'/purchases/'.$id.'/receive')
        ->assertOk()->assertJsonPath('data.status', Purchase::STATUS_RECEIVED);

    expect((float) IngredientStock::query()->where('ingredient_id', $this->rice->id)->value('stock_qty'))->toBe(20000.0);

    $this->withToken($this->token)->putJson($this->base.'/purchases/'.$id, [
        'supplier_id' => $this->supplier->id,
        'purchased_at' => now()->toDateString(),
        'items' => [['ingredient_id' => $this->rice->id, 'unit_id' => $this->kg->id, 'qty' => 5, 'unit_cost' => 90]],
    ])->assertOk();

    expect((float) IngredientStock::query()->where('ingredient_id', $this->rice->id)->value('stock_qty'))->toBe(5000.0);

    $this->withToken($this->token)->postJson($this->base.'/purchases/'.$id.'/delete', ['reason_code' => 'bad'])->assertUnprocessable();
    $this->withToken($this->token)->postJson($this->base.'/purchases/'.$id.'/delete', ['reason_code' => 'entry_mistake'])->assertOk();

    expect((float) IngredientStock::query()->where('ingredient_id', $this->rice->id)->value('stock_qty'))->toBe(0.0);
});

it('runs a stock count from start to close and refuses a second open count', function (): void {
    $id = draftPurchase($this);
    $this->withToken($this->token)->postJson($this->base.'/purchases/'.$id.'/receive')->assertOk();

    $this->withToken($this->token)->postJson($this->base.'/stock-counts')->assertCreated();
    $this->withToken($this->token)->postJson($this->base.'/stock-counts')->assertUnprocessable();

    $open = $this->withToken($this->token)->getJson($this->base.'/stock-counts')->assertOk()->json('data.open');
    $countId = $open['id'];
    $itemId = $open['items'][0]['id'];

    $this->withToken($this->token)->putJson($this->base.'/stock-counts/'.$countId.'/items', ['counted' => [$itemId => 9000]])->assertOk();
    $this->withToken($this->token)->postJson($this->base.'/stock-counts/'.$countId.'/close')->assertOk();

    expect(StockCount::query()->find($countId)->status)->toBe(StockCount::STATUS_CLOSED)
        ->and((float) IngredientStock::query()->where('ingredient_id', $this->rice->id)->value('stock_qty'))->toBe(9000.0);

    $this->withToken($this->token)->postJson($this->base.'/stock-counts/'.$countId.'/delete', ['reason_code' => 'entry_mistake'])->assertOk();
    expect((float) IngredientStock::query()->where('ingredient_id', $this->rice->id)->value('stock_qty'))->toBe(10000.0);
});

it('deletes a wastage entry and puts the stock back', function (): void {
    $id = draftPurchase($this);
    $this->withToken($this->token)->postJson($this->base.'/purchases/'.$id.'/receive')->assertOk();

    $wasteId = $this->withToken($this->token)->postJson($this->base.'/inventory/wastage', [
        'ingredient_id' => $this->rice->id, 'unit_id' => $this->kg->id, 'qty' => 2, 'reason_code' => 'spoiled',
    ])->assertCreated()->json('data.id');

    expect((float) IngredientStock::query()->where('ingredient_id', $this->rice->id)->value('stock_qty'))->toBe(8000.0);

    $this->withToken($this->token)->postJson($this->base.'/wastage/'.$wasteId.'/delete', ['reason_code' => 'entry_mistake'])->assertOk();

    expect((float) IngredientStock::query()->where('ingredient_id', $this->rice->id)->value('stock_qty'))->toBe(10000.0);
});

it('manages ingredient categories and protects one that is in use', function (): void {
    $id = $this->withToken($this->token)->postJson('/api/v1/ingredient-categories', ['name' => 'Grains'])->assertCreated()->json('data.id');
    $this->withToken($this->token)->putJson('/api/v1/ingredient-categories/'.$id, ['name' => 'Grains and pulses'])->assertOk();

    $this->rice->update(['ingredient_category_id' => $id]);
    $this->withToken($this->token)->deleteJson('/api/v1/ingredient-categories/'.$id)->assertUnprocessable();

    $this->rice->update(['ingredient_category_id' => null]);
    $this->withToken($this->token)->deleteJson('/api/v1/ingredient-categories/'.$id)->assertOk();

    expect(IngredientCategory::query()->count())->toBe(0);
});

it('deactivates an ingredient', function (): void {
    $this->withToken($this->token)->deleteJson('/api/v1/ingredients/'.$this->rice->id)->assertOk();
    expect($this->rice->fresh()->is_active)->toBeFalse();
});

it('keeps inventory actions to permitted roles', function (): void {
    $rider = User::factory()->role('rider', $this->branch->id)->create();
    $token = $rider->createToken('staff', ['staff'])->plainTextToken;
    $this->withToken($token)->postJson($this->base.'/stock-counts')->assertForbidden();
});

it('lists transfers for the branch', function (): void {
    $this->withToken($this->token)->getJson($this->base.'/stock-transfers')->assertOk()->assertJsonPath('data', []);
});
