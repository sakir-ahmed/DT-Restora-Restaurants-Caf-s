<?php

declare(strict_types=1);

use App\Actions\Inventory\ReceivePurchaseAction;
use App\Models\Branch;
use App\Models\Ingredient;
use App\Models\IngredientStock;
use App\Models\MenuItem;
use App\Models\Purchase;
use App\Models\Recipe;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Services\CostingService;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UnitSeeder;

beforeEach(function (): void {
    $this->seed([RoleSeeder::class, UnitSeeder::class]);

    $this->branch = Branch::factory()->create(['code' => 'BR01']);
    $this->kitchen = User::factory()->role('kitchen-manager', $this->branch->id)->create();

    $this->gram = Unit::query()->where('symbol', 'g')->firstOrFail();
    $this->kg = Unit::query()->where('symbol', 'kg')->firstOrFail();
});

function createPurchaseWithStock(Branch $branch, Ingredient $ingredient, $unit, float $qty, float $unitCost): Purchase
{
    $purchase = Purchase::query()->create([
        'branch_id' => $branch->id,
        'supplier_id' => Supplier::factory()->create()->id,
        'purchased_at' => now()->toDateString(),
        'status' => Purchase::STATUS_DRAFT,
    ]);

    $purchase->items()->create([
        'ingredient_id' => $ingredient->id,
        'unit_id' => $unit->id,
        'qty' => $qty,
        'unit_cost' => $unitCost,
        'line_total' => round($qty * $unitCost, 2),
    ]);

    app(ReceivePurchaseAction::class)->execute($purchase);

    return $purchase;
}

it('converts purchase units to the base unit on receipt', function (): void {
    $ingredient = Ingredient::factory()->create(['unit_id' => $this->gram->id]);

    createPurchaseWithStock($this->branch, $ingredient, $this->kg, 5, 300); // 5 kg x 300 = 1500 g

    $stock = IngredientStock::query()
        ->where('branch_id', $this->branch->id)
        ->where('ingredient_id', $ingredient->id)
        ->first();

    expect((float) $stock->stock_qty)->toBe(5000.0)
        ->and((float) $stock->avg_cost)->toBe(0.3); // 300 per kg = 0.3 per gram
});

it('updates the weighted average cost after another purchase at a different price', function (): void {
    $ingredient = Ingredient::factory()->create(['unit_id' => $this->gram->id]);

    createPurchaseWithStock($this->branch, $ingredient, $this->kg, 2, 100); // 2000 g @ 0.10/g
    createPurchaseWithStock($this->branch, $ingredient, $this->kg, 2, 200); // 2000 g @ 0.20/g

    $stock = IngredientStock::query()
        ->where('branch_id', $this->branch->id)
        ->where('ingredient_id', $ingredient->id)
        ->first();

    expect((float) $stock->stock_qty)->toBe(4000.0)
        ->and((float) $stock->avg_cost)->toBe(0.15); // (2000*0.1 + 2000*0.2) / 4000
});

it('receiving a purchase twice does not double the stock', function (): void {
    $ingredient = Ingredient::factory()->create(['unit_id' => $this->gram->id]);

    $purchase = createPurchaseWithStock($this->branch, $ingredient, $this->kg, 3, 100);

    app(ReceivePurchaseAction::class)->execute($purchase);
    app(ReceivePurchaseAction::class)->execute($purchase);

    $stock = IngredientStock::query()
        ->where('branch_id', $this->branch->id)
        ->where('ingredient_id', $ingredient->id)
        ->first();

    expect((float) $stock->stock_qty)->toBe(3000.0)
        ->and(StockMovement::query()->where('type', 'purchase')->count())->toBe(1);
});

it('updates the recipe cost after a purchase price change', function (): void {
    $ingredient = Ingredient::factory()->create(['unit_id' => $this->gram->id]);
    $item = MenuItem::factory()->create([
        'branch_id' => $this->branch->id,
        'base_price' => '200.00',
    ]);

    $recipe = Recipe::query()->create(['menu_item_id' => $item->id]);
    $recipe->items()->create(['ingredient_id' => $ingredient->id, 'qty' => 250]); // 250 g

    createPurchaseWithStock($this->branch, $ingredient, $this->kg, 1, 200); // 0.2/g
    $costBefore = app(CostingService::class)->recipeCost($item->fresh(), $this->branch->id);

    createPurchaseWithStock($this->branch, $ingredient, $this->kg, 1, 400); // 0.4/g
    $costAfter = app(CostingService::class)->recipeCost($item->fresh(), $this->branch->id);

    expect($costBefore['cost'])->toBe('50.00') // 250 g x 0.2
        ->and($costAfter['cost'])->toBe('75.00') // 250 g x 0.3 weighted average
        ->and($costBefore['food_cost_percent'])->toBe('25.00')
        ->and($costAfter['food_cost_percent'])->toBe('37.50');
});

it('saves recipes through the controller with authorization', function (): void {
    $ingredient = Ingredient::factory()->create(['unit_id' => $this->gram->id]);
    $item = MenuItem::factory()->create(['branch_id' => $this->branch->id]);

    $response = $this->actingAs($this->kitchen, 'staff')->post('/admin/recipes', [
        'menu_item_id' => $item->id,
        'lines' => [
            ['ingredient_id' => $ingredient->id, 'qty' => 100],
        ],
    ]);

    $response->assertRedirect();

    expect($item->fresh()->recipe->items)->toHaveCount(1)
        ->and((float) $item->fresh()->recipe->items->first()->qty)->toBe(100.0);
});

it('rejects recipe writes from a cashier', function (): void {
    $cashier = User::factory()->role('cashier', $this->branch->id)->create();
    $item = MenuItem::factory()->create(['branch_id' => $this->branch->id]);

    $response = $this->actingAs($cashier, 'staff')->post('/admin/recipes', [
        'menu_item_id' => $item->id,
        'lines' => [['ingredient_id' => 1, 'qty' => 10]],
    ]);

    $response->assertForbidden();
});
