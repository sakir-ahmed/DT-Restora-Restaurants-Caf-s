<?php

declare(strict_types=1);

use App\Actions\Inventory\ReceivePurchaseAction;
use App\Models\Branch;
use App\Models\Ingredient;
use App\Models\IngredientStock;
use App\Models\Journal;
use App\Models\Purchase;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Services\StockService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UnitSeeder;

beforeEach(function (): void {
    $this->seed([ChartOfAccountsSeeder::class, RoleSeeder::class, UnitSeeder::class]);

    $this->branch = Branch::factory()->create(['code' => 'BR01']);
    $this->owner = User::factory()->role('owner', $this->branch->id)->create();
    $this->kg = Unit::query()->where('symbol', 'kg')->firstOrFail();
    $this->gram = Unit::query()->where('symbol', 'g')->firstOrFail();
    $this->ingredient = Ingredient::factory()->create(['unit_id' => $this->gram->id]);
    $this->otherIngredient = Ingredient::factory()->create(['unit_id' => $this->gram->id]);
});

function makePurchase(string $status = Purchase::STATUS_DRAFT): Purchase
{
    $purchase = Purchase::query()->create([
        'branch_id' => test()->branch->id,
        'supplier_id' => Supplier::factory()->create()->id,
        'invoice_no' => 'INV-1',
        'purchased_at' => now()->toDateString(),
        'status' => Purchase::STATUS_DRAFT,
    ]);

    $purchase->items()->create([
        'ingredient_id' => test()->ingredient->id,
        'unit_id' => test()->kg->id,
        'qty' => 5,
        'unit_cost' => 300,
        'line_total' => 1500,
    ]);

    if ($status === Purchase::STATUS_RECEIVED) {
        app(ReceivePurchaseAction::class)->execute($purchase);
    }

    if ($status === Purchase::STATUS_CANCELLED) {
        $purchase->update(['status' => Purchase::STATUS_CANCELLED]);
    }

    return $purchase->fresh();
}

function currentStock(): float
{
    return (float) (IngredientStock::query()
        ->where('branch_id', test()->branch->id)
        ->where('ingredient_id', test()->ingredient->id)
        ->value('stock_qty') ?? 0);
}

it('fully edits a draft purchase and replaces its lines', function (): void {
    $purchase = makePurchase();

    $this->actingAs($this->owner, 'staff')
        ->put(route('admin.purchases.update', $purchase), [
            'supplier_id' => null,
            'invoice_no' => 'INV-2',
            'purchased_at' => '2026-01-15',
            'note' => 'fixed',
            'items' => [
                ['ingredient_id' => $this->otherIngredient->id, 'unit_id' => $this->kg->id, 'qty' => 2, 'unit_cost' => 100],
            ],
        ])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success');

    $purchase->refresh()->load('items');

    expect($purchase->supplier_id)->toBeNull()
        ->and($purchase->invoice_no)->toBe('INV-2')
        ->and($purchase->note)->toBe('fixed')
        ->and($purchase->items)->toHaveCount(1)
        ->and($purchase->items->first()->ingredient_id)->toBe($this->otherIngredient->id)
        ->and((float) $purchase->items->first()->line_total)->toBe(200.0)
        ->and(currentStock())->toBe(0.0);
});

it('corrects a received purchase: stock, average cost and the journal follow the corrected lines', function (): void {
    $purchase = makePurchase(Purchase::STATUS_RECEIVED); // 5 kg @ 300 = 1500
    expect(currentStock())->toBe(5000.0);

    $this->actingAs($this->owner, 'staff')
        ->put(route('admin.purchases.update', $purchase), [
            'invoice_no' => 'INV-FIXED',
            'purchased_at' => now()->toDateString(),
            'supplier_id' => $purchase->supplier_id,
            'items' => [
                ['ingredient_id' => $this->ingredient->id, 'unit_id' => $this->kg->id, 'qty' => 3, 'unit_cost' => 200],
            ],
        ])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success');

    $purchase->refresh()->load('items');
    $stock = IngredientStock::query()->where('ingredient_id', $this->ingredient->id)->firstOrFail();

    expect($purchase->status)->toBe(Purchase::STATUS_RECEIVED)
        ->and((float) $purchase->total)->toBe(600.0)
        ->and($purchase->items)->toHaveCount(1)
        ->and((float) $purchase->items->first()->qty)->toBe(3.0)
        ->and((float) $stock->stock_qty)->toBe(3000.0)
        ->and((float) $stock->avg_cost)->toBe(0.2)
        ->and((float) $stock->last_cost)->toBe(0.2);

    $original = Journal::query()->where('source_type', $purchase->getMorphClass())->where('source_id', $purchase->id)->get();
    $posted = $original->where('status', Journal::STATUS_POSTED);
    expect($original->where('status', Journal::STATUS_REVERSED))->toHaveCount(1)
        ->and($posted)->toHaveCount(1)
        ->and((float) $posted->first()->lines->sum('debit'))->toBe(600.0);

    expect(StockMovement::query()->where('reference_id', $purchase->id)->where('type', 'purchase')->count())->toBe(3);
});

it('refuses a correction when the received goods were already used and changes nothing', function (): void {
    $purchase = makePurchase(Purchase::STATUS_RECEIVED); // 5000 g in stock

    app(StockService::class)->adjust($this->branch->id, $this->ingredient->id, -4500.0, 'sale'); // 500 g left

    $this->actingAs($this->owner, 'staff')
        ->put(route('admin.purchases.update', $purchase), [
            'purchased_at' => now()->toDateString(),
            'items' => [
                ['ingredient_id' => $this->ingredient->id, 'unit_id' => $this->kg->id, 'qty' => 1, 'unit_cost' => 300],
            ],
        ])
        ->assertSessionHas('error');

    $purchase->refresh()->load('items');

    expect(currentStock())->toBe(500.0)
        ->and((float) $purchase->items->first()->qty)->toBe(5.0)
        ->and((float) $purchase->total)->toBe(1500.0)
        ->and(Journal::query()->where('source_id', $purchase->id)->where('status', Journal::STATUS_POSTED)->count())->toBe(1);
});

it('moves stock to the new ingredient when the ingredient was wrong', function (): void {
    $purchase = makePurchase(Purchase::STATUS_RECEIVED);

    $this->actingAs($this->owner, 'staff')
        ->put(route('admin.purchases.update', $purchase), [
            'purchased_at' => now()->toDateString(),
            'items' => [
                ['ingredient_id' => $this->otherIngredient->id, 'unit_id' => $this->kg->id, 'qty' => 5, 'unit_cost' => 300],
            ],
        ])
        ->assertSessionHasNoErrors();

    $other = IngredientStock::query()->where('ingredient_id', $this->otherIngredient->id)->firstOrFail();

    expect(currentStock())->toBe(0.0)
        ->and((float) $other->stock_qty)->toBe(5000.0)
        ->and((float) $other->avg_cost)->toBe(0.3);
});

it('refuses to edit a cancelled purchase', function (): void {
    $purchase = makePurchase(Purchase::STATUS_CANCELLED);

    $this->actingAs($this->owner, 'staff')
        ->put(route('admin.purchases.update', $purchase), ['invoice_no' => 'X'])
        ->assertSessionHas('error');

    expect($purchase->fresh()->invoice_no)->toBe('INV-1');
});

it('validates draft lines on edit', function (): void {
    $purchase = makePurchase();

    $this->actingAs($this->owner, 'staff')
        ->put(route('admin.purchases.update', $purchase), [
            'purchased_at' => now()->toDateString(),
            'items' => [['ingredient_id' => $this->ingredient->id, 'unit_id' => $this->kg->id, 'qty' => 0, 'unit_cost' => 10]],
        ])
        ->assertSessionHasErrors('items.0.qty');

    expect($purchase->fresh()->items)->toHaveCount(1);
});

it('blocks staff without purchase permission and other branches from editing', function (): void {
    $purchase = makePurchase();
    $waiter = User::factory()->role('waiter', $this->branch->id)->create();

    $this->actingAs($waiter, 'staff')
        ->put(route('admin.purchases.update', $purchase), ['invoice_no' => 'HACK'])
        ->assertForbidden();

    $otherBranch = Branch::factory()->create(['code' => 'BR02']);
    $outsider = User::factory()->role('kitchen-manager', $otherBranch->id)->create();

    $this->actingAs($outsider, 'staff')
        ->put(route('admin.purchases.update', $purchase), ['invoice_no' => 'HACK', 'purchased_at' => now()->toDateString(), 'items' => []])
        ->assertNotFound();

    expect($purchase->fresh()->invoice_no)->toBe('INV-1');
});
