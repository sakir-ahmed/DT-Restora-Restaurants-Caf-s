<?php

declare(strict_types=1);

use App\Actions\Sales\ApproveOrderVoidAction;
use App\Actions\Sales\CloseShiftAction;
use App\Actions\Sales\CreateOrderAction;
use App\Actions\Sales\OpenShiftAction;
use App\Actions\Sales\PayOrderAction;
use App\Actions\Sales\RequestOrderVoidAction;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\Branch;
use App\Models\Ingredient;
use App\Models\IngredientStock;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Recipe;
use App\Models\RestaurantTable;
use App\Models\Shift;
use App\Models\Unit;
use App\Models\User;
use App\Services\InvoiceNumberService;
use App\Services\StockService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UnitSeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->seed([ChartOfAccountsSeeder::class, RoleSeeder::class, UnitSeeder::class]);

    $this->branch = Branch::factory()->create([
        'code' => 'BR01',
        'vat_percent' => '5.00',
        'vat_inclusive' => false,
        'service_charge_percent' => '10.00',
    ]);

    $this->cashier = User::factory()->role('cashier', $this->branch->id)->create();
    $this->manager = User::factory()->role('branch-manager', $this->branch->id)->create();

    $this->category = MenuCategory::factory()->create(['branch_id' => $this->branch->id]);
    $this->item = MenuItem::factory()->create([
        'branch_id' => $this->branch->id,
        'menu_category_id' => $this->category->id,
        'base_price' => '200.00',
    ]);

    // Ingredient + stock + recipe so stock deduction is exercised.
    $this->ingredient = Ingredient::factory()->create(['unit_id' => Unit::where('symbol', 'g')->firstOrFail()->id]);

    IngredientStock::query()->create([
        'branch_id' => $this->branch->id,
        'ingredient_id' => $this->ingredient->id,
        'stock_qty' => 10000,
        'avg_cost' => 0.1,
    ]);

    $recipe = Recipe::query()->create(['menu_item_id' => $this->item->id]);
    $recipe->items()->create(['ingredient_id' => $this->ingredient->id, 'qty' => 100]); // 100 g per serving

    $this->shift = Shift::query()->create([
        'branch_id' => $this->branch->id,
        'user_id' => $this->cashier->id,
        'terminal_code' => 'T01',
        'opening_cash' => 1000,
        'opened_at' => now(),
    ]);
});

function createPosOrder($test, array $overrides = []): Order
{
    $action = app(CreateOrderAction::class);

    return $action->execute(
        branch: $test->branch,
        type: OrderType::Takeaway,
        lines: [[
            'menu_item_id' => $test->item->id,
            'quantity' => $overrides['quantity'] ?? 2,
        ]],
        cashier: $test->cashier,
        shift: $test->shift,
        terminalCode: 'T01',
        discount: $overrides['discount'] ?? null,
    );
}

it('creates an order with correct VAT, service charge, rounding and stock deduction', function (): void {
    $order = createPosOrder($this);

    // subtotal 400, service 10% = 40, VAT 5% on 440 = 22, total 462 (already whole)
    expect((float) $order->subtotal)->toBe(400.0)
        ->and((float) $order->service_charge)->toBe(40.0)
        ->and((float) $order->vat_amount)->toBe(22.0)
        ->and((float) $order->total)->toBe(462.0)
        ->and($order->invoice_no)->toBe('BR01-T01-'.now()->format('Ymd').'-0001')
        ->and((float) $order->food_cost)->toBe(20.0); // 2 x 100 g x 0.1

    $stock = IngredientStock::query()
        ->where('branch_id', $this->branch->id)
        ->where('ingredient_id', $this->ingredient->id)
        ->first();

    expect((float) $stock->stock_qty)->toBe(9800.0); // 10000 - 200
});

it('rounds the grand total to the nearest taka and stores the difference', function (): void {
    $this->item->update(['base_price' => '99.99']);

    $order = createPosOrder($this);

    // subtotal 199.98, service 19.998, vat 10.9989 => raw 230.98 => total 231
    expect((float) $order->total)->toBe(231.0)
        ->and((float) $order->rounding)->toBe(0.02);
});

it('applies a percent discount before service charge and VAT', function (): void {
    $order = createPosOrder($this, [
        'discount' => ['type' => 'percent', 'value' => 10],
    ]);

    // subtotal 400 - 40 = 360, service 36, vat 19.8 => 415.8 => 416
    expect((float) $order->discount_amount)->toBe(40.0)
        ->and((float) $order->total)->toBe(416.0);
});

it('allows a fixed discount within the limit', function (): void {
    $order = createPosOrder($this, [
        'discount' => ['type' => 'fixed', 'value' => 20],
    ]);

    expect((float) $order->discount_amount)->toBe(20.0);
});

it('applies a line discount, custom vat and an extra charge', function (): void {
    $order = app(CreateOrderAction::class)->execute(
        branch: $this->branch,
        type: OrderType::Takeaway,
        lines: [[
            'menu_item_id' => $this->item->id,
            'quantity' => 2,
            'discount' => ['type' => 'percent', 'value' => 10],
            'vat_percent' => 0,
            'extra_charge' => 20,
        ]],
        cashier: $this->cashier,
        shift: $this->shift,
        terminalCode: 'T01',
    );

    // Gross 400, line discount 40, extra 20, service 10% of 360 = 36, custom VAT 0, service VAT 1.80.
    expect((float) $order->discount_amount)->toBe(40.0)
        ->and((float) $order->extra_charge)->toBe(20.0)
        ->and((float) $order->service_charge)->toBe(36.0)
        ->and((float) $order->vat_amount)->toBe(1.8)
        ->and((float) $order->total)->toBe(418.0);
});

it('rejects a fixed discount above the limit without the override permission', function (): void {
    expect(fn () => createPosOrder($this, ['discount' => ['type' => 'fixed', 'value' => 80]]))
        ->toThrow(ValidationException::class);
});

it('rejects discounts above the limit without the override permission', function (): void {
    expect(fn () => createPosOrder($this, ['discount' => ['type' => 'percent', 'value' => 25]]))
        ->toThrow(ValidationException::class);

    // A manager with discounts.override may apply it.
    $action = app(CreateOrderAction::class);

    $order = $action->execute(
        branch: $this->branch,
        type: OrderType::Takeaway,
        lines: [['menu_item_id' => $this->item->id, 'quantity' => 2]],
        cashier: $this->manager,
        discount: ['type' => 'percent', 'value' => 25],
    );

    expect((float) $order->discount_amount)->toBe(100.0);
});

it('keeps invoice numbers sequential per branch and terminal', function (): void {
    $order1 = createPosOrder($this);
    $order2 = createPosOrder($this);

    $suffix = now()->format('Ymd');

    expect($order1->invoice_no)->toBe("BR01-T01-{$suffix}-0001")
        ->and($order2->invoice_no)->toBe("BR01-T01-{$suffix}-0002");
});

it('is idempotent by client id', function (): void {
    $action = app(CreateOrderAction::class);

    $lines = [['menu_item_id' => $this->item->id, 'quantity' => 1]];

    $first = $action->execute($this->branch, OrderType::Takeaway, $lines, clientId: 'TEST-CLIENT-1');
    $second = $action->execute($this->branch, OrderType::Takeaway, $lines, clientId: 'TEST-CLIENT-1');

    expect($second->id)->toBe($first->id)
        ->and(Order::count())->toBe(1);
});

it('completes a cash payment with change and marks the order paid', function (): void {
    $order = createPosOrder($this);

    $payment = app(PayOrderAction::class)->executeCash($order, 500, $this->cashier);

    expect((float) $payment->amount)->toBe(462.0)
        ->and((float) $payment->change_due)->toBe(38.0)
        ->and($order->fresh()->status)->toBe(OrderStatus::Paid)
        ->and($order->fresh()->paid_at)->not->toBeNull();
});

it('rejects a payment below the total and double payment', function (): void {
    $order = createPosOrder($this);

    expect(fn () => app(PayOrderAction::class)->executeCash($order, 100, $this->cashier))
        ->toThrow(ValidationException::class);

    app(PayOrderAction::class)->executeCash($order, 462, $this->cashier);

    expect(fn () => app(PayOrderAction::class)->executeCash($order->fresh(), 462, $this->cashier))
        ->toThrow(ValidationException::class);
});

it('rejects an order when ingredient stock is insufficient', function (): void {
    IngredientStock::query()
        ->where('branch_id', $this->branch->id)
        ->where('ingredient_id', $this->ingredient->id)
        ->update(['stock_qty' => 50]); // only half a serving

    expect(fn () => createPosOrder($this))->toThrow(ValidationException::class);

    expect(Order::count())->toBe(0);
});

it('voids an order through request and approval and restores stock', function (): void {
    $order = createPosOrder($this);

    app(PayOrderAction::class)->executeCash($order, 462, $this->cashier);

    $request = app(RequestOrderVoidAction::class)->execute($order, $this->cashier, 'wrong_item');

    expect($request->status)->toBe('pending');

    app(ApproveOrderVoidAction::class)->execute($request, $this->manager);

    $stock = IngredientStock::query()
        ->where('branch_id', $this->branch->id)
        ->where('ingredient_id', $this->ingredient->id)
        ->first();

    expect($order->fresh()->status)->toBe(OrderStatus::Void)
        ->and((float) $stock->stock_qty)->toBe(10000.0)
        ->and($request->fresh()->approved_by)->toBe($this->manager->id);
});

it('keeps stock consistent when two orders are created concurrently', function (): void {
    IngredientStock::query()
        ->where('branch_id', $this->branch->id)
        ->where('ingredient_id', $this->ingredient->id)
        ->update(['stock_qty' => 300]); // exactly 3 servings

    $action = new CreateOrderAction(
        app(StockService::class),
        app(InvoiceNumberService::class),
    );

    $results = collect();

    foreach (range(1, 3) as $i) {
        $results->push($action->execute(
            branch: $this->branch,
            type: OrderType::Takeaway,
            lines: [['menu_item_id' => $this->item->id, 'quantity' => 1]],
            cashier: $this->cashier,
        ));
    }

    $stock = IngredientStock::query()
        ->where('branch_id', $this->branch->id)
        ->where('ingredient_id', $this->ingredient->id)
        ->first();

    expect($results)->toHaveCount(3)
        ->and((float) $stock->stock_qty)->toBe(0.0);

    // A fourth order must fail, never negative.
    expect(fn () => $action->execute(
        branch: $this->branch,
        type: OrderType::Takeaway,
        lines: [['menu_item_id' => $this->item->id, 'quantity' => 1]],
        cashier: $this->cashier,
    ))->toThrow(ValidationException::class);
});

it('opens and closes a cashier shift with expected versus counted cash', function (): void {
    $order = createPosOrder($this);

    app(PayOrderAction::class)->executeCash($order, 500, $this->cashier);

    // Opening a second shift while one is open fails.
    expect(fn () => app(OpenShiftAction::class)->execute($this->branch, $this->cashier, 0))
        ->toThrow(ValidationException::class);

    $closed = app(CloseShiftAction::class)->execute($this->shift, 1400);

    expect((float) $closed->expected_cash)->toBe(1462.0) // 1000 opening + 462 cash sale
        ->and((float) $closed->difference)->toBe(-62.0)
        ->and($closed->closed_at)->not->toBeNull();
});

it('drives the whole flow through the controller endpoints', function (): void {
    $response = $this->actingAs($this->cashier, 'staff')->post('/admin/orders', [
        'order_type' => 'takeaway',
        'items' => [['menu_item_id' => $this->item->id, 'quantity' => 1]],
        'terminal' => 'T01',
    ]);

    $response->assertRedirect();

    $order = Order::query()->firstOrFail();

    expect((float) $order->total)->toBe(231.0); // 200 + 20 + 10

    $payResponse = $this->actingAs($this->cashier, 'staff')->post("/admin/orders/{$order->id}/pay", [
        'tendered' => 250,
    ]);

    $payResponse->assertRedirect();

    expect($order->fresh()->status)->toBe(OrderStatus::Paid);

    // Receipt renders
    $receipt = $this->actingAs($this->cashier, 'staff')->get("/admin/orders/{$order->id}/receipt");
    $receipt->assertOk();

    // Cashier cannot approve their own void; manager can.
    $this->actingAs($this->cashier, 'staff')->post("/admin/orders/{$order->id}/void-request", [
        'reason_code' => 'wrong_item',
    ]);

    $this->actingAs($this->cashier, 'staff')->post("/admin/orders/{$order->id}/approve-void")->assertForbidden();

    $this->actingAs($this->manager, 'staff')->post("/admin/orders/{$order->id}/approve-void");

    expect($order->fresh()->status)->toBe(OrderStatus::Void);
});

it('unlocks the POS only with the staff PIN', function (): void {
    $this->cashier->update(['pin' => '1234']);

    $this->actingAs($this->cashier, 'staff')->postJson('/admin/pos/unlock', [
        'pin' => '0000',
    ])->assertUnprocessable()->assertJsonValidationErrors('pin');

    $this->actingAs($this->cashier, 'staff')->postJson('/admin/pos/unlock', [
        'pin' => '1234',
    ])->assertOk()->assertJsonPath('ok', true);
});

it('saves a dine-in order without a table', function (): void {
    $this->actingAs($this->cashier, 'staff')->post('/admin/orders', [
        'order_type' => 'dine_in',
        'restaurant_table_id' => null,
        'items' => [['menu_item_id' => $this->item->id, 'quantity' => 1]],
    ])->assertSessionHasNoErrors();

    $order = Order::query()->latest('id')->firstOrFail();

    expect($order->order_type)->toBe(OrderType::DineIn)
        ->and($order->restaurant_table_id)->toBeNull();
});

it('changes the items of an unpaid order and keeps stock and the invoice consistent', function (): void {
    $order = createPosOrder($this);
    $stock = fn (): float => (float) IngredientStock::query()->where('ingredient_id', $this->ingredient->id)->value('stock_qty');

    expect($stock())->toBe(9800.0);

    $this->actingAs($this->cashier, 'staff')->put("/admin/orders/{$order->id}", [
        'order_type' => 'takeaway',
        'items' => [['menu_item_id' => $this->item->id, 'quantity' => 3]],
    ])->assertSessionHasNoErrors();

    $order->refresh()->load('items');

    expect($order->invoice_no)->toBe($order->invoice_no)
        ->and($order->items)->toHaveCount(1)
        ->and($order->items->first()->quantity)->toBe(3)
        ->and((float) $order->subtotal)->toBe(600.0)
        ->and($stock())->toBe(9700.0);
});

it('refuses to change an order that is already paid', function (): void {
    $order = createPosOrder($this);
    $order->update(['status' => OrderStatus::Paid->value]);

    $this->actingAs($this->cashier, 'staff')->put("/admin/orders/{$order->id}", [
        'order_type' => 'takeaway',
        'items' => [['menu_item_id' => $this->item->id, 'quantity' => 2]],
    ])->assertSessionHasErrors('order');

    expect($order->fresh()->items)->toHaveCount(1);
});

it('lists unpaid orders on the POS screen so they can be edited', function (): void {
    $order = createPosOrder($this);

    $this->actingAs($this->cashier, 'staff')->get('/admin/pos')
        ->assertInertia(fn ($page) => $page->has('openOrders', 1)->where('openOrders.0.id', $order->id));
});

it('rejects POS access for roles without the permission', function (): void {
    $rider = User::factory()->role('rider', $this->branch->id)->create();

    $this->actingAs($rider, 'staff')->get('/admin/pos')->assertForbidden();
    $this->actingAs($rider, 'staff')->post('/admin/orders', [
        'order_type' => 'takeaway',
        'items' => [['menu_item_id' => $this->item->id, 'quantity' => 1]],
    ])->assertForbidden();
});

it('releases a table with no running order without needing the void permission', function (): void {
    $table = RestaurantTable::factory()->create(['branch_id' => $this->branch->id, 'status' => 'occupied']);

    $this->actingAs($this->cashier, 'staff')->post("/admin/pos/tables/{$table->id}/release")->assertRedirect();

    expect($table->fresh()->status)->toBe('free');
});

it('force clears an unpaid table order only with the void permission', function (): void {
    $table = RestaurantTable::factory()->create(['branch_id' => $this->branch->id]);

    $order = app(CreateOrderAction::class)->execute(
        branch: $this->branch,
        type: OrderType::DineIn,
        lines: [['menu_item_id' => $this->item->id, 'quantity' => 1]],
        cashier: $this->cashier,
        shift: $this->shift,
        terminalCode: 'T01',
        tableId: $table->id,
    );

    expect($table->fresh()->status)->toBe('occupied');

    $this->actingAs($this->cashier, 'staff')->post("/admin/pos/tables/{$table->id}/release")->assertSessionHasErrors('table');
    $this->actingAs($this->cashier, 'staff')->post("/admin/pos/tables/{$table->id}/release", ['force' => true, 'reason_code' => 'other'])->assertForbidden();

    $this->actingAs($this->manager, 'staff')->post("/admin/pos/tables/{$table->id}/release", ['force' => true, 'reason_code' => 'other'])->assertRedirect();

    expect($table->fresh()->status)->toBe('free')
        ->and($order->fresh()->status)->toBe(OrderStatus::Void);
});

it('force clears an unpaid order that has no table', function (): void {
    $order = createPosOrder($this);

    $this->actingAs($this->cashier, 'staff')->post("/admin/pos/orders/{$order->id}/clear", ['reason_code' => 'other'])->assertForbidden();

    $this->actingAs($this->manager, 'staff')->post("/admin/pos/orders/{$order->id}/clear", ['reason_code' => 'other'])->assertRedirect();

    expect($order->fresh()->status)->toBe(OrderStatus::Void);

    $this->actingAs($this->manager, 'staff')->post("/admin/pos/orders/{$order->id}/clear", ['reason_code' => 'other'])->assertSessionHasErrors('order');
});
