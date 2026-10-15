<?php

declare(strict_types=1);

use App\Actions\Inventory\ApproveStockTransferAction;
use App\Actions\Inventory\ReceiveStockTransferAction;
use App\Models\Branch;
use App\Models\Ingredient;
use App\Models\IngredientStock;
use App\Models\StockCount;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\Unit;
use App\Models\User;
use App\Models\Wastage;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UnitSeeder;

beforeEach(function (): void {
    $this->seed([RoleSeeder::class, UnitSeeder::class]);

    $this->branch = Branch::factory()->create(['code' => 'BR01']);
    $this->kitchen = User::factory()->role('kitchen-manager', $this->branch->id)->create();

    $this->gram = Unit::query()->where('symbol', 'g')->firstOrFail();
    $this->kg = Unit::query()->where('symbol', 'kg')->firstOrFail();

    $this->ingredient = Ingredient::factory()->create(['unit_id' => $this->gram->id]);

    IngredientStock::query()->create([
        'branch_id' => $this->branch->id,
        'ingredient_id' => $this->ingredient->id,
        'stock_qty' => 1000,
        'avg_cost' => 0.5,
        'last_cost' => 0.5,
    ]);
});

it('logs wastage with the cost calculated server-side', function (): void {
    $response = $this->actingAs($this->kitchen, 'staff')->post('/admin/wastage', [
        'ingredient_id' => $this->ingredient->id,
        'unit_id' => $this->kg->id,
        'qty' => 0.2, // 200 g
        'reason_code' => 'spoiled',
        'note' => 'Fridge was off',
    ]);

    $response->assertRedirect();

    $wastage = Wastage::query()->firstOrFail();

    expect((float) $wastage->cost)->toBe(100.0) // 200 g x 0.5
        ->and($wastage->reason_code)->toBe('spoiled')
        ->and((float) $wastage->fresh()->ingredient->stocks->firstWhere('branch_id', $this->branch->id)->stock_qty)->toBe(800.0);
});

it('requires a known reason code', function (): void {
    $response = $this->actingAs($this->kitchen, 'staff')->post('/admin/wastage', [
        'ingredient_id' => $this->ingredient->id,
        'unit_id' => $this->kg->id,
        'qty' => 0.1,
        'reason_code' => 'no-reason',
    ]);

    $response->assertSessionHasErrors('reason_code');
});

it('never lets stock go negative when the branch forbids it', function (): void {
    $response = $this->actingAs($this->kitchen, 'staff')->post('/admin/wastage', [
        'ingredient_id' => $this->ingredient->id,
        'unit_id' => $this->kg->id,
        'qty' => 5, // 5000 g, only 1000 g available
        'reason_code' => 'spoiled',
    ]);

    $response->assertStatus(500);

    $stock = IngredientStock::query()
        ->where('branch_id', $this->branch->id)
        ->where('ingredient_id', $this->ingredient->id)
        ->first();

    expect((float) $stock->stock_qty)->toBe(1000.0)
        ->and(Wastage::count())->toBe(0)
        ->and(StockMovement::count())->toBe(0);
});

it('allows negative stock when the branch opts in', function (): void {
    $this->branch->update(['allow_negative_stock' => true]);

    $this->actingAs($this->kitchen, 'staff')->post('/admin/wastage', [
        'ingredient_id' => $this->ingredient->id,
        'unit_id' => $this->kg->id,
        'qty' => 5,
        'reason_code' => 'spoiled',
    ]);

    $stock = IngredientStock::query()
        ->where('branch_id', $this->branch->id)
        ->where('ingredient_id', $this->ingredient->id)
        ->first();

    expect((float) $stock->stock_qty)->toBe(-4000.0);
});

it('applies stock count variances on close', function (): void {
    $count = StockCount::query()->create([
        'branch_id' => $this->branch->id,
        'status' => 'open',
        'user_id' => $this->kitchen->id,
    ]);

    $item = $count->items()->create([
        'ingredient_id' => $this->ingredient->id,
        'system_qty' => 1000,
    ]);

    $this->actingAs($this->kitchen, 'staff')->put("/admin/stock-counts/{$count->id}/items", [
        'counted' => [$item->id => '940'],
    ]);

    $response = $this->actingAs($this->kitchen, 'staff')->post("/admin/stock-counts/{$count->id}/close");

    $response->assertRedirect();

    $stock = IngredientStock::query()
        ->where('branch_id', $this->branch->id)
        ->where('ingredient_id', $this->ingredient->id)
        ->first();

    expect($count->fresh()->status)->toBe('closed')
        ->and((float) $stock->stock_qty)->toBe(940.0)
        ->and((float) $count->fresh()->items->first()->variance_qty)->toBe(-60.0);
});

it('moves transfer stock exactly once through request, approve and receive', function (): void {
    $branchB = Branch::factory()->create(['code' => 'BR02']);

    $transfer = StockTransfer::query()->create([
        'from_branch_id' => $this->branch->id,
        'to_branch_id' => $branchB->id,
        'status' => StockTransfer::STATUS_REQUESTED,
        'requested_by' => $this->kitchen->id,
    ]);

    $transfer->items()->create([
        'ingredient_id' => $this->ingredient->id,
        'unit_id' => $this->kg->id,
        'qty' => 0.5, // 500 g
    ]);

    // Receive before approve must fail.
    try {
        app(ReceiveStockTransferAction::class)->execute($transfer->fresh(), $this->kitchen);
        $this->fail('Receiving an unapproved transfer should throw.');
    } catch (LogicException) {
        // expected
    }

    app(ApproveStockTransferAction::class)->execute($transfer->fresh(), $this->kitchen);
    app(ReceiveStockTransferAction::class)->execute($transfer->fresh(), $this->kitchen);

    // Receiving again must be a no-op.
    try {
        app(ReceiveStockTransferAction::class)->execute($transfer->fresh(), $this->kitchen);
        $this->fail('Receiving twice should throw.');
    } catch (LogicException) {
        // expected
    }

    $source = IngredientStock::query()->where('branch_id', $this->branch->id)->where('ingredient_id', $this->ingredient->id)->first();
    $destination = IngredientStock::query()->where('branch_id', $branchB->id)->where('ingredient_id', $this->ingredient->id)->first();

    expect((float) $source->stock_qty)->toBe(500.0)
        ->and((float) $destination->stock_qty)->toBe(500.0)
        ->and((float) $destination->avg_cost)->toBe(0.5)
        ->and(StockMovement::query()->where('type', 'transfer_out')->count())->toBe(1)
        ->and(StockMovement::query()->where('type', 'transfer_in')->count())->toBe(1);
});
