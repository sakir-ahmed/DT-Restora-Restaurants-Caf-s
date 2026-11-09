<?php

declare(strict_types=1);

use App\Actions\Inventory\CloseStockCountAction;
use App\Actions\Inventory\LogWastageAction;
use App\Actions\Inventory\ReceivePurchaseAction;
use App\Actions\Inventory\ReceiveStockTransferAction;
use App\Actions\Payroll\RecordSalaryAdvanceAction;
use App\Actions\Sales\CreateOrderAction;
use App\Actions\Sales\PayOrderAction;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\Ingredient;
use App\Models\IngredientStock;
use App\Models\Journal;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Purchase;
use App\Models\Reservation;
use App\Models\RestaurantTable;
use App\Models\SalaryAdvance;
use App\Models\Setting;
use App\Models\StockCount;
use App\Models\StockTransfer;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\Wastage;
use App\Services\Accounting\AccountingService;
use App\Services\StockService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UnitSeeder;

beforeEach(function (): void {
    $this->seed([ChartOfAccountsSeeder::class, RoleSeeder::class, UnitSeeder::class]);

    $this->branch = Branch::factory()->create(['code' => 'BR01', 'vat_percent' => '0.00', 'service_charge_percent' => '0.00']);
    $this->owner = User::factory()->role('owner', $this->branch->id)->create();
    $this->kg = Unit::query()->where('symbol', 'kg')->firstOrFail();
    $this->gram = Unit::query()->where('symbol', 'g')->firstOrFail();
    $this->ingredient = Ingredient::factory()->create(['unit_id' => $this->gram->id]);
});

function stockOf(Ingredient $ingredient): float
{
    return (float) (IngredientStock::query()
        ->where('branch_id', test()->branch->id)
        ->where('ingredient_id', $ingredient->id)
        ->value('stock_qty') ?? 0);
}

function receivedPurchase(): Purchase
{
    $purchase = Purchase::query()->create([
        'branch_id' => test()->branch->id,
        'supplier_id' => Supplier::factory()->create()->id,
        'invoice_no' => 'INV-9',
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

    return app(ReceivePurchaseAction::class)->execute($purchase)->fresh();
}

function deletePayload(string $reason = 'entry_mistake'): array
{
    return ['reason_code' => $reason, 'note' => 'typed twice'];
}

function trialBalanceNet(): float
{
    return round((float) Journal::query()->where('status', '!=', Journal::STATUS_DRAFT)->withSum('lines', 'debit')->get()->sum('lines_sum_debit')
        - (float) Journal::query()->where('status', '!=', Journal::STATUS_DRAFT)->withSum('lines', 'credit')->get()->sum('lines_sum_credit'), 2);
}

it('deletes a received purchase: stock, cost and journal go back, the row is kept as deleted', function (): void {
    $purchase = receivedPurchase();
    expect(stockOf($this->ingredient))->toBe(5000.0);

    $this->actingAs($this->owner, 'staff')
        ->delete(route('admin.purchases.destroy', $purchase), deletePayload())
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success');

    expect(Purchase::query()->find($purchase->id))->toBeNull();

    $kept = Purchase::withTrashed()->findOrFail($purchase->id);

    expect($kept->trashed())->toBeTrue()
        ->and($kept->deleted_by)->toBe($this->owner->id)
        ->and($kept->delete_reason)->toBe('entry_mistake')
        ->and(stockOf($this->ingredient))->toBe(0.0)
        ->and(Journal::query()->where('source_type', $purchase->getMorphClass())->where('source_id', $purchase->id)->where('status', Journal::STATUS_POSTED)->count())->toBe(0)
        ->and(Journal::query()->where('status', Journal::STATUS_REVERSED)->count())->toBe(1);
});

it('refuses to delete a received purchase whose goods were already used', function (): void {
    $purchase = receivedPurchase();

    app(LogWastageAction::class)->execute($this->branch, $this->ingredient, $this->kg, 4.0, 'spoiled', null, $this->owner);

    $this->actingAs($this->owner, 'staff')
        ->delete(route('admin.purchases.destroy', $purchase), deletePayload())
        ->assertSessionHas('error');

    expect(Purchase::query()->find($purchase->id))->not->toBeNull()
        ->and(stockOf($this->ingredient))->toBe(1000.0);
});

it('deletes a draft purchase without touching stock', function (): void {
    $purchase = receivedPurchase();
    $purchase->forceFill(['status' => Purchase::STATUS_DRAFT])->save();

    $this->actingAs($this->owner, 'staff')
        ->delete(route('admin.purchases.destroy', $purchase), deletePayload('duplicate_entry'))
        ->assertSessionHas('success');

    expect(Purchase::withTrashed()->find($purchase->id)->trashed())->toBeTrue();
});

it('requires a valid reason to delete', function (): void {
    $purchase = receivedPurchase();

    $this->actingAs($this->owner, 'staff')
        ->delete(route('admin.purchases.destroy', $purchase), ['reason_code' => 'nonsense'])
        ->assertSessionHasErrors('reason_code');

    expect(Purchase::query()->find($purchase->id))->not->toBeNull();
});

it('follows the show-deleted setting on the purchases list', function (): void {
    $purchase = receivedPurchase();

    $this->actingAs($this->owner, 'staff')->delete(route('admin.purchases.destroy', $purchase), deletePayload());

    Setting::current()->update(['show_deleted_items' => true]);
    $this->actingAs($this->owner, 'staff')->get(route('admin.purchases.index'))
        ->assertInertia(fn ($page) => $page->has('purchases', 1)->where('purchases.0.deleted_at', fn ($value) => $value !== null));

    Setting::current()->update(['show_deleted_items' => false]);
    $this->actingAs($this->owner, 'staff')->get(route('admin.purchases.index'))
        ->assertInertia(fn ($page) => $page->has('purchases', 0));
});

it('does not allow editing a deleted purchase', function (): void {
    $purchase = receivedPurchase();
    $this->actingAs($this->owner, 'staff')->delete(route('admin.purchases.destroy', $purchase), deletePayload());

    Setting::current()->update(['show_deleted_items' => true]);

    $this->actingAs($this->owner, 'staff')
        ->put(route('admin.purchases.update', $purchase->id), [
            'invoice_no' => 'X',
            'purchased_at' => now()->toDateString(),
            'items' => [['ingredient_id' => $this->ingredient->id, 'unit_id' => $this->kg->id, 'qty' => 1, 'unit_cost' => 1]],
        ]);

    expect(Purchase::withTrashed()->find($purchase->id)->invoice_no)->toBe('INV-9');
});

it('adds an ingredient from the purchase form', function (): void {
    $this->actingAs($this->owner, 'staff')
        ->postJson(route('admin.purchases.ingredients.store'), ['name' => 'Saffron', 'unit_id' => $this->gram->id])
        ->assertCreated()
        ->assertJsonPath('name', 'Saffron')
        ->assertJsonPath('unit.symbol', 'g');

    expect(Ingredient::query()->where('name', 'Saffron')->where('is_active', true)->exists())->toBeTrue();

    $this->actingAs($this->owner, 'staff')
        ->postJson(route('admin.purchases.ingredients.store'), ['name' => ''])
        ->assertUnprocessable();
});

it('deletes a wastage entry: stock returns and the journal is reversed', function (): void {
    receivedPurchase();
    $wastage = app(LogWastageAction::class)->execute($this->branch, $this->ingredient, $this->kg, 1.0, 'spoiled', null, $this->owner);
    expect(stockOf($this->ingredient))->toBe(4000.0);

    $this->actingAs($this->owner, 'staff')
        ->delete(route('admin.wastage.destroy', $wastage), deletePayload())
        ->assertSessionHas('success');

    expect(stockOf($this->ingredient))->toBe(5000.0)
        ->and(Wastage::query()->find($wastage->id))->toBeNull()
        ->and(Wastage::withTrashed()->find($wastage->id)->delete_reason)->toBe('entry_mistake')
        ->and(Journal::query()->where('source_type', $wastage->getMorphClass())->where('source_id', $wastage->id)->where('status', Journal::STATUS_POSTED)->count())->toBe(0);

    Setting::current()->update(['show_deleted_items' => false]);
    $this->actingAs($this->owner, 'staff')->get(route('admin.wastage.index'))
        ->assertInertia(fn ($page) => $page->has('wastages', 0));
});

it('deletes an expense and reverses its journal', function (): void {
    $expense = Expense::query()->create([
        'branch_id' => $this->branch->id,
        'expense_date' => now()->toDateString(),
        'category' => 'rent',
        'amount' => 500,
        'user_id' => $this->owner->id,
    ]);
    app(AccountingService::class)->postExpense($expense, $this->owner);

    $this->actingAs($this->owner, 'staff')
        ->delete(route('admin.expenses.destroy', $expense), deletePayload('wrong_amount'))
        ->assertSessionHas('success');

    expect(Expense::query()->find($expense->id))->toBeNull()
        ->and(Expense::withTrashed()->find($expense->id)->delete_reason)->toBe('wrong_amount')
        ->and(Journal::query()->where('source_type', $expense->getMorphClass())->where('source_id', $expense->id)->where('status', Journal::STATUS_POSTED)->count())->toBe(0);
});

it('deletes a due sale from the dues page and follows the show-deleted setting', function (): void {
    $cashier = User::factory()->role('cashier', $this->branch->id)->create();
    $category = MenuCategory::factory()->create(['branch_id' => $this->branch->id]);
    $item = MenuItem::factory()->create(['branch_id' => $this->branch->id, 'menu_category_id' => $category->id, 'base_price' => '200.00']);
    $customer = Customer::query()->create(['name' => 'Rahim', 'mobile' => '01711111111', 'is_active' => true]);

    $order = app(CreateOrderAction::class)->execute($this->branch, OrderType::Takeaway, [['menu_item_id' => $item->id, 'quantity' => 2]], $cashier, customer: $customer);
    app(PayOrderAction::class)->executeDue($order, [['method' => 'cash', 'amount' => 100]], $cashier);

    $this->actingAs($this->owner, 'staff')
        ->post(route('admin.orders.delete', $order), ['reason_code' => 'duplicate_order'])
        ->assertSessionHas('success');

    expect($order->fresh()->status)->toBe(OrderStatus::Void);

    Setting::current()->update(['show_deleted_items' => true]);
    $this->actingAs($this->owner, 'staff')->get(route('admin.dues.index'))
        ->assertInertia(fn ($page) => $page->has('orders', 1)->where('orders.0.deleted', true)->where('orders.0.balance', 0));

    Setting::current()->update(['show_deleted_items' => false]);
    $this->actingAs($this->owner, 'staff')->get(route('admin.dues.index'))
        ->assertInertia(fn ($page) => $page->has('orders', 0));

    $this->actingAs($this->owner, 'staff')->get(route('admin.orders.index'))
        ->assertInertia(fn ($page) => $page->has('orders', 0));

    Setting::current()->update(['show_deleted_items' => true]);
    $this->actingAs($this->owner, 'staff')->get(route('admin.orders.index'))
        ->assertInertia(fn ($page) => $page->has('orders', 1));
});

it('saves the show-deleted setting from the settings page', function (): void {
    $this->actingAs($this->owner, 'staff')
        ->put(route('admin.settings.update'), [
            'discount_limit_percent' => 10,
            'wastage_spike_multiplier' => 1.5,
            'pos_pin_minutes' => 5,
            'delivery_otp_enabled' => true,
            'show_deleted_items' => false,
        ])
        ->assertSessionHasNoErrors();

    expect(Setting::showsDeletedItems())->toBeFalse();
});

// ---- stock counts, transfers, reservations, salary advances ----

it('deletes an open stock count without touching stock and lets a new one start', function (): void {
    receivedPurchase();

    $this->actingAs($this->owner, 'staff')->post(route('admin.stock-counts.store'));
    $count = StockCount::query()->firstOrFail();

    $this->actingAs($this->owner, 'staff')
        ->delete(route('admin.stock-counts.destroy', $count), deletePayload())
        ->assertSessionHas('success');

    expect(StockCount::query()->count())->toBe(0)
        ->and(stockOf($this->ingredient))->toBe(5000.0);

    $this->actingAs($this->owner, 'staff')->post(route('admin.stock-counts.store'))->assertSessionHas('success');
});

it('deletes a closed stock count and takes its variance back out of stock', function (): void {
    receivedPurchase();

    $this->actingAs($this->owner, 'staff')->post(route('admin.stock-counts.store'));
    $count = StockCount::query()->firstOrFail();
    $item = $count->items()->firstOrFail();
    $item->update(['counted_qty' => 4500]);
    app(CloseStockCountAction::class)->execute($count, $this->owner);
    expect(stockOf($this->ingredient))->toBe(4500.0);

    $this->actingAs($this->owner, 'staff')
        ->delete(route('admin.stock-counts.destroy', $count), deletePayload())
        ->assertSessionHas('success');

    expect(stockOf($this->ingredient))->toBe(5000.0)
        ->and(StockCount::withTrashed()->find($count->id)->trashed())->toBeTrue();
});

it('refuses to delete a closed stock count when stock is too low to take the variance back', function (): void {
    receivedPurchase();

    $this->actingAs($this->owner, 'staff')->post(route('admin.stock-counts.store'));
    $count = StockCount::query()->firstOrFail();
    $count->items()->firstOrFail()->update(['counted_qty' => 6000]);
    app(CloseStockCountAction::class)->execute($count, $this->owner);

    app(LogWastageAction::class)->execute($this->branch, $this->ingredient, $this->kg, 5.5, 'spoiled', null, $this->owner);

    $this->actingAs($this->owner, 'staff')
        ->delete(route('admin.stock-counts.destroy', $count), deletePayload())
        ->assertSessionHas('error');

    expect(StockCount::query()->find($count->id))->not->toBeNull()
        ->and(stockOf($this->ingredient))->toBe(500.0);
});

it('deletes a received stock transfer and returns the stock to the source branch', function (): void {
    $other = Branch::factory()->create(['code' => 'BR02']);
    $admin = User::factory()->role('owner')->create();

    app(StockService::class)->adjust($this->branch->id, $this->ingredient->id, 5000.0, 'purchase', unitCost: 0.3);

    $transfer = StockTransfer::query()->create(['from_branch_id' => $this->branch->id, 'to_branch_id' => $other->id, 'status' => StockTransfer::STATUS_APPROVED]);
    $transfer->items()->create(['ingredient_id' => $this->ingredient->id, 'unit_id' => $this->kg->id, 'qty' => 2]);
    app(ReceiveStockTransferAction::class)->execute($transfer, $admin);

    $stockAt = fn (Branch $branch): float => (float) IngredientStock::query()->where('branch_id', $branch->id)->where('ingredient_id', $this->ingredient->id)->value('stock_qty');
    expect($stockAt($this->branch))->toBe(3000.0)->and($stockAt($other))->toBe(2000.0);

    $this->actingAs($admin, 'staff')
        ->delete(route('admin.stock-transfers.destroy', $transfer), deletePayload())
        ->assertSessionHas('success');

    expect($stockAt($this->branch))->toBe(5000.0)
        ->and($stockAt($other))->toBe(0.0)
        ->and(StockTransfer::query()->find($transfer->id))->toBeNull();
});

it('refuses to delete a received transfer when the destination already used the goods', function (): void {
    $other = Branch::factory()->create(['code' => 'BR02']);
    $admin = User::factory()->role('owner')->create();

    app(StockService::class)->adjust($this->branch->id, $this->ingredient->id, 5000.0, 'purchase', unitCost: 0.3);
    $transfer = StockTransfer::query()->create(['from_branch_id' => $this->branch->id, 'to_branch_id' => $other->id, 'status' => StockTransfer::STATUS_APPROVED]);
    $transfer->items()->create(['ingredient_id' => $this->ingredient->id, 'unit_id' => $this->kg->id, 'qty' => 2]);
    app(ReceiveStockTransferAction::class)->execute($transfer, $admin);

    app(LogWastageAction::class)->execute($other, $this->ingredient, $this->kg, 1.5, 'spoiled', null, $admin);

    $this->actingAs($admin, 'staff')
        ->delete(route('admin.stock-transfers.destroy', $transfer), deletePayload())
        ->assertSessionHas('error');

    expect(StockTransfer::query()->find($transfer->id))->not->toBeNull();
});

it('deletes a plain booking but not one with booking money or seated guests', function (): void {
    $table = RestaurantTable::factory()->create(['branch_id' => $this->branch->id]);

    $make = fn (array $attributes = []): Reservation => Reservation::query()->create($attributes + [
        'branch_id' => $this->branch->id,
        'guest_name' => 'Karim',
        'guest_mobile' => '01711111111',
        'party_size' => 2,
        'starts_at' => now()->addHour(),
        'ends_at' => now()->addHours(2),
        'created_by' => $this->owner->id,
    ]);

    $plain = $make();
    $plain->tables()->sync([$table->id => ['position' => 0]]);

    $this->actingAs($this->owner, 'staff')
        ->delete(route('admin.reservations.destroy', $plain), deletePayload())
        ->assertSessionHas('success');

    expect(Reservation::query()->find($plain->id))->toBeNull()
        ->and(Reservation::clashes([$table->id], now()->addHour(), now()->addHours(2)))->toBeFalse();

    $withMoney = $make(['deposit_status' => Reservation::DEPOSIT_HELD, 'deposit_amount' => 200]);
    $this->actingAs($this->owner, 'staff')->delete(route('admin.reservations.destroy', $withMoney), deletePayload())->assertSessionHas('error');

    $seated = $make(['status' => Reservation::SEATED]);
    $this->actingAs($this->owner, 'staff')->delete(route('admin.reservations.destroy', $seated), deletePayload())->assertSessionHas('error');

    expect(Reservation::query()->whereKey([$withMoney->id, $seated->id])->count())->toBe(2);
});

it('deletes a salary advance, reverses its journal and stops it counting against pay', function (): void {
    $employee = Employee::factory()->create(['branch_id' => $this->branch->id]);

    $advance = app(RecordSalaryAdvanceAction::class)->execute($this->branch, $employee, ['amount' => 1000, 'advanced_on' => now()->toDateString()], $this->owner);

    $this->actingAs($this->owner, 'staff')
        ->delete(route('admin.payroll.advances.destroy', $advance), deletePayload('wrong_amount'))
        ->assertSessionHas('success');

    expect(SalaryAdvance::query()->find($advance->id))->toBeNull()
        ->and(SalaryAdvance::withTrashed()->find($advance->id)->delete_reason)->toBe('wrong_amount')
        ->and(Journal::query()->where('source_type', $advance->getMorphClass())->where('source_id', $advance->id)->where('status', Journal::STATUS_POSTED)->count())->toBe(0);

    Setting::current()->update(['show_deleted_items' => true]);
    $this->actingAs($this->owner, 'staff')->get(route('admin.payroll.index'))
        ->assertInertia(fn ($page) => $page->has('advances', 1)->where('advances.0.deleted', true));

    Setting::current()->update(['show_deleted_items' => false]);
    $this->actingAs($this->owner, 'staff')->get(route('admin.payroll.index'))
        ->assertInertia(fn ($page) => $page->has('advances', 0));
});
