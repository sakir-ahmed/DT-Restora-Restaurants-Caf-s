<?php

declare(strict_types=1);

use App\Actions\Sales\CollectDueAction;
use App\Actions\Sales\CreateOrderAction;
use App\Actions\Sales\PayOrderAction;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Ingredient;
use App\Models\IngredientStock;
use App\Models\Journal;
use App\Models\JournalLine;
use App\Models\LoyaltyEntry;
use App\Models\LoyaltySetting;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Recipe;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UnitSeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->seed([ChartOfAccountsSeeder::class, RoleSeeder::class, UnitSeeder::class]);

    $this->branch = Branch::factory()->create(['code' => 'BR01', 'vat_percent' => '0.00', 'service_charge_percent' => '0.00']);
    $this->cashier = User::factory()->role('cashier', $this->branch->id)->create();
    $this->kitchen = User::factory()->role('kitchen-manager', $this->branch->id)->create();

    $category = MenuCategory::factory()->create(['branch_id' => $this->branch->id]);
    $this->item = MenuItem::factory()->create([
        'branch_id' => $this->branch->id,
        'menu_category_id' => $category->id,
        'base_price' => '200.00',
    ]);

    $this->customer = Customer::query()->create(['name' => 'Rahim', 'mobile' => '01711111111', 'is_active' => true]);
});

function dueOrder($test, ?Customer $customer = null, int $quantity = 2): Order
{
    return app(CreateOrderAction::class)->execute(
        branch: $test->branch,
        type: OrderType::Takeaway,
        lines: [['menu_item_id' => $test->item->id, 'quantity' => $quantity]],
        cashier: $test->cashier,
        customer: $customer,
    );
}

it('completes a sale on due and books the balance as a receivable', function (): void {
    $order = dueOrder($this, $this->customer);

    $payments = app(PayOrderAction::class)->executeDue($order, [['method' => 'cash', 'amount' => 100]], $this->cashier);

    $order->refresh();

    expect($payments)->toHaveCount(1)
        ->and($order->status)->toBe(OrderStatus::Paid)
        ->and($order->is_due)->toBeTrue()
        ->and($order->balanceDue())->toBe(300.0);

    $journal = Journal::query()->where('source_id', $order->id)->firstOrFail();
    $lines = $journal->lines()->with('account')->get();

    expect(round((float) $lines->sum('debit'), 2))->toBe(round((float) $lines->sum('credit'), 2))
        ->and(round((float) $lines->filter(fn ($line) => $line->account->subtype === 'receivable')->sum('debit'), 2))->toBe(300.0);
});

it('allows a due sale with nothing received', function (): void {
    $order = dueOrder($this, $this->customer);

    app(PayOrderAction::class)->executeDue($order, [], $this->cashier);

    expect($order->fresh()->is_due)->toBeTrue()
        ->and($order->fresh()->balanceDue())->toBe(400.0)
        ->and(Journal::query()->where('source_id', $order->id)->exists())->toBeTrue();
});

it('needs a customer to sell on due', function (): void {
    $order = dueOrder($this);

    expect(fn () => app(PayOrderAction::class)->executeDue($order, [], $this->cashier))->toThrow(ValidationException::class);

    expect($order->fresh()->status)->not->toBe(OrderStatus::Paid);
});

it('refuses a due when the amount received already covers the bill', function (): void {
    $order = dueOrder($this, $this->customer);

    expect(fn () => app(PayOrderAction::class)->executeDue($order, [['method' => 'cash', 'amount' => 400]], $this->cashier))
        ->toThrow(ValidationException::class);
});

it('collects a due in parts and clears it when the balance reaches zero', function (): void {
    $order = dueOrder($this, $this->customer);
    app(PayOrderAction::class)->executeDue($order, [['method' => 'cash', 'amount' => 100]], $this->cashier);

    app(CollectDueAction::class)->execute($order, 'cash', 150, null, $this->cashier);

    expect($order->fresh()->is_due)->toBeTrue()
        ->and($order->fresh()->balanceDue())->toBe(150.0);

    app(CollectDueAction::class)->execute($order, 'card', 150, 'SLIP-1', $this->cashier);

    expect($order->fresh()->is_due)->toBeFalse()
        ->and($order->fresh()->balanceDue())->toBe(0.0)
        ->and(Payment::query()->where('order_id', $order->id)->where('is_due_collection', true)->count())->toBe(2);

    $collection = Journal::query()->where('source_type', (new Payment)->getMorphClass())->get();

    expect($collection)->toHaveCount(2);

    $collection->each(function (Journal $journal): void {
        $lines = $journal->lines()->get();

        expect(round((float) $lines->sum('debit'), 2))->toBe(round((float) $lines->sum('credit'), 2));
    });
});

it('rejects collecting more than is owed or on a sale that is not due', function (): void {
    $order = dueOrder($this, $this->customer);
    app(PayOrderAction::class)->executeDue($order, [['method' => 'cash', 'amount' => 100]], $this->cashier);

    expect(fn () => app(CollectDueAction::class)->execute($order, 'cash', 301, null, $this->cashier))->toThrow(ValidationException::class);

    $paid = dueOrder($this, $this->customer);
    app(PayOrderAction::class)->executeCash($paid, 400, $this->cashier);

    expect(fn () => app(CollectDueAction::class)->execute($paid, 'cash', 10, null, $this->cashier))->toThrow(ValidationException::class);
});

it('completes a due and collects it through the screens', function (): void {
    $order = dueOrder($this, $this->customer);

    $this->actingAs($this->cashier, 'staff')
        ->post("/admin/orders/{$order->id}/due", ['lines' => [['method' => 'cash', 'amount' => '50']]])
        ->assertRedirect();

    expect($order->fresh()->is_due)->toBeTrue();

    $this->actingAs($this->cashier, 'staff')
        ->get('/admin/dues')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/dues/Index')
            ->has('orders', 1)
            ->where('orders.0.balance', 350));

    $this->actingAs($this->cashier, 'staff')
        ->post("/admin/dues/{$order->id}/collect", ['method' => 'cash', 'amount' => '350'])
        ->assertRedirect();

    expect($order->fresh()->is_due)->toBeFalse();
});

it('keeps due collection to staff who can take orders and to their own branch', function (): void {
    $order = dueOrder($this, $this->customer);
    app(PayOrderAction::class)->executeDue($order, [], $this->cashier);

    $this->actingAs($this->kitchen, 'staff')
        ->post("/admin/dues/{$order->id}/collect", ['method' => 'cash', 'amount' => '10'])
        ->assertForbidden();

    $otherBranch = Branch::factory()->create();
    $outsider = User::factory()->role('cashier', $otherBranch->id)->create();

    $this->actingAs($outsider, 'staff')
        ->post("/admin/dues/{$order->id}/collect", ['method' => 'cash', 'amount' => '10'])
        ->assertNotFound();
});

it('sells with empty stock only when the branch allows it', function (): void {
    $ingredient = Ingredient::factory()->create(['unit_id' => Unit::where('symbol', 'g')->firstOrFail()->id]);
    IngredientStock::query()->create(['branch_id' => $this->branch->id, 'ingredient_id' => $ingredient->id, 'stock_qty' => 0, 'avg_cost' => 0.1]);
    Recipe::query()->create(['menu_item_id' => $this->item->id])->items()->create(['ingredient_id' => $ingredient->id, 'qty' => 100]);

    expect(fn () => dueOrder($this, null, 1))->toThrow(ValidationException::class);

    $this->actingAs($this->cashier, 'staff')
        ->get('/admin/pos')
        ->assertInertia(fn ($page) => $page->where('stockBook.allow_negative', false)->where("stockBook.stock.{$ingredient->id}.name", $ingredient->name));

    $this->branch->update(['allow_negative_stock' => true]);

    $order = dueOrder($this, null, 1);

    expect($order->exists)->toBeTrue()
        ->and((float) IngredientStock::query()->where('ingredient_id', $ingredient->id)->value('stock_qty'))->toBe(-100.0);
});

it('attaches a customer typed after the order was sent when completing on due', function (): void {
    $order = dueOrder($this);

    $this->actingAs($this->cashier, 'staff')
        ->post("/admin/orders/{$order->id}/due", ['lines' => [], 'customer_mobile' => '01722222222', 'customer_name' => 'Karim'])
        ->assertRedirect();

    $order->refresh();

    expect($order->is_due)->toBeTrue()
        ->and($order->customer?->name)->toBe('Karim');
});

it('saves a fresh ticket on due in two steps: the order with the modal customer, then the due', function (): void {
    $this->actingAs($this->cashier, 'staff')->post('/admin/orders', [
        'order_type' => 'takeaway',
        'items' => [['menu_item_id' => $this->item->id, 'quantity' => 1]],
        'customer_mobile' => '01744444444',
        'customer_name' => 'Salma',
        'terminal' => 'T01',
    ])->assertRedirect();

    $order = Order::query()->latest('id')->firstOrFail();

    $this->actingAs($this->cashier, 'staff')
        ->post("/admin/orders/{$order->id}/due", ['lines' => [['method' => 'cash', 'amount' => '50']], 'customer_mobile' => '01744444444', 'customer_name' => 'Salma'])
        ->assertRedirect();

    $order->refresh();

    expect($order->is_due)->toBeTrue()
        ->and($order->customer?->name)->toBe('Salma')
        ->and($order->balanceDue())->toBe(150.0);
});

it('suggests past customers by mobile or name for the POS auto-fill', function (): void {
    Customer::query()->create(['name' => 'Karim Uddin', 'mobile' => '8801733333333', 'is_active' => true]);

    $this->actingAs($this->cashier, 'staff')
        ->getJson('/admin/pos/customers?q=01733')
        ->assertOk()
        ->assertJsonPath('0.name', 'Karim Uddin')
        ->assertJsonPath('0.mobile', '01733333333');

    $this->actingAs($this->cashier, 'staff')
        ->getJson('/admin/pos/customers?q=Karim')
        ->assertJsonCount(1);

    $this->actingAs($this->cashier, 'staff')->getJson('/admin/pos/customers?q=K')->assertExactJson([]);

    $this->actingAs($this->kitchen, 'staff')->getJson('/admin/pos/customers?q=Karim')->assertForbidden();
});

function receivableBalance(): float
{
    return round((float) JournalLine::query()
        ->whereHas('account', fn ($query) => $query->where('subtype', 'receivable'))
        ->get()
        ->sum(fn ($line) => (float) $line->debit - (float) $line->credit), 2);
}

it('revises a due sale: new items, new balance, payments and books follow', function (): void {
    $order = dueOrder($this, $this->customer);
    app(PayOrderAction::class)->executeDue($order, [['method' => 'cash', 'amount' => 100]], $this->cashier);
    app(CollectDueAction::class)->execute($order, 'cash', 50, null, $this->cashier);

    expect(receivableBalance())->toBe(250.0);

    $this->actingAs($this->cashier, 'staff')
        ->put(route('admin.dues.update', $order), [
            'order_type' => 'takeaway',
            'items' => [['menu_item_id' => $this->item->id, 'quantity' => 3]],
        ])
        ->assertRedirect(route('admin.dues.index'));

    $order->refresh();

    expect((float) $order->total)->toBe(600.0)
        ->and($order->is_due)->toBeTrue()
        ->and($order->items)->toHaveCount(1)
        ->and($order->items->first()->quantity)->toBe(3)
        ->and($order->balanceDue())->toBe(450.0)
        ->and(Payment::query()->where('order_id', $order->id)->count())->toBe(2)
        ->and(receivableBalance())->toBe(450.0);

    Journal::query()->with('lines')->get()->each(function (Journal $journal): void {
        expect(round((float) $journal->lines->sum('debit'), 2))->toBe(round((float) $journal->lines->sum('credit'), 2));
    });

    app(CollectDueAction::class)->execute($order, 'cash', 450, null, $this->cashier);

    expect($order->fresh()->is_due)->toBeFalse()
        ->and(receivableBalance())->toBe(0.0);
});

it('settles a due sale whose new total equals what was already received', function (): void {
    $order = dueOrder($this, $this->customer);
    app(PayOrderAction::class)->executeDue($order, [['method' => 'cash', 'amount' => 200]], $this->cashier);

    $this->actingAs($this->cashier, 'staff')
        ->put(route('admin.dues.update', $order), [
            'order_type' => 'takeaway',
            'items' => [['menu_item_id' => $this->item->id, 'quantity' => 1]],
        ])
        ->assertRedirect(route('admin.dues.index'));

    expect($order->fresh()->is_due)->toBeFalse()
        ->and(receivableBalance())->toBe(0.0);
});

it('refuses to revise a due sale below what was already received', function (): void {
    $order = dueOrder($this, $this->customer);
    app(PayOrderAction::class)->executeDue($order, [['method' => 'cash', 'amount' => 300]], $this->cashier);

    $this->actingAs($this->cashier, 'staff')
        ->put(route('admin.dues.update', $order), [
            'order_type' => 'takeaway',
            'items' => [['menu_item_id' => $this->item->id, 'quantity' => 1]],
        ])
        ->assertSessionHasErrors('items');

    expect((float) $order->fresh()->total)->toBe(400.0)
        ->and($order->fresh()->items->first()->quantity)->toBe(2)
        ->and(receivableBalance())->toBe(100.0);
});

it('does not revise a sale that is not paid yet through the dues route', function (): void {
    $order = dueOrder($this, $this->customer);

    $this->actingAs($this->cashier, 'staff')
        ->put(route('admin.dues.update', $order), [
            'order_type' => 'takeaway',
            'items' => [['menu_item_id' => $this->item->id, 'quantity' => 1]],
        ])
        ->assertSessionHasErrors('order');
});

it('offers a due sale to the POS for editing with its paid and balance', function (): void {
    $order = dueOrder($this, $this->customer);
    app(PayOrderAction::class)->executeDue($order, [['method' => 'cash', 'amount' => 100]], $this->cashier);

    $this->actingAs($this->cashier, 'staff')
        ->get(route('admin.pos.index', ['edit' => $order->id]))
        ->assertInertia(fn ($page) => $page
            ->where('editTarget.id', $order->id)
            ->where('editTarget.paid', 100)
            ->where('editTarget.balance', 300));
});

it('edits a fully paid sale: more items turn the difference into a due, books stay balanced', function (): void {
    $order = dueOrder($this, $this->customer);
    app(PayOrderAction::class)->executeCash($order, 400, $this->cashier);

    expect(receivableBalance())->toBe(0.0);

    $this->actingAs($this->cashier, 'staff')
        ->put(route('admin.orders.update', $order), [
            'order_type' => 'takeaway',
            'items' => [['menu_item_id' => $this->item->id, 'quantity' => 3]],
        ])
        ->assertRedirect(route('admin.orders.index'))
        ->assertSessionHasNoErrors();

    $order->refresh();

    expect((float) $order->total)->toBe(600.0)
        ->and($order->status)->toBe(OrderStatus::Paid)
        ->and($order->is_due)->toBeTrue()
        ->and($order->balanceDue())->toBe(200.0)
        ->and(receivableBalance())->toBe(200.0);

    Journal::query()->with('lines')->get()->each(function (Journal $journal): void {
        expect(round((float) $journal->lines->sum('debit'), 2))->toBe(round((float) $journal->lines->sum('credit'), 2));
    });
});

it('edits a fully paid sale to the same total without creating a due', function (): void {
    $order = dueOrder($this, $this->customer);
    app(PayOrderAction::class)->executeCash($order, 400, $this->cashier);

    $this->actingAs($this->cashier, 'staff')
        ->put(route('admin.orders.update', $order), [
            'order_type' => 'takeaway',
            'items' => [['menu_item_id' => $this->item->id, 'quantity' => 2]],
            'note' => 'same bill',
        ])
        ->assertSessionHasNoErrors();

    expect($order->fresh()->is_due)->toBeFalse()
        ->and($order->fresh()->note)->toBe('same bill')
        ->and(receivableBalance())->toBe(0.0);
});

it('refuses to cut a paid sale below what was received, and a rise needs a customer', function (): void {
    $order = dueOrder($this, $this->customer);
    app(PayOrderAction::class)->executeCash($order, 400, $this->cashier);

    $this->actingAs($this->cashier, 'staff')
        ->put(route('admin.orders.update', $order), [
            'order_type' => 'takeaway',
            'items' => [['menu_item_id' => $this->item->id, 'quantity' => 1]],
        ])
        ->assertSessionHasErrors('items');

    expect((float) $order->fresh()->total)->toBe(400.0);

    $walkIn = dueOrder($this);
    app(PayOrderAction::class)->executeCash($walkIn, 400, $this->cashier);

    $this->actingAs($this->cashier, 'staff')
        ->put(route('admin.orders.update', $walkIn), [
            'order_type' => 'takeaway',
            'items' => [['menu_item_id' => $this->item->id, 'quantity' => 3]],
        ])
        ->assertSessionHasErrors('items');

    expect((float) $walkIn->fresh()->total)->toBe(400.0)
        ->and($walkIn->fresh()->is_due)->toBeFalse();
});

it('does not count loyalty points twice when a paid sale is edited again', function (): void {
    LoyaltySetting::query()->update(['is_enabled' => true, 'earn_rate' => 10]);

    $order = dueOrder($this, $this->customer);
    app(PayOrderAction::class)->executeCash($order, 400, $this->cashier);

    $balance = fn (): int => (int) LoyaltyEntry::query()->where('customer_id', $this->customer->id)->sum('points');
    $first = $balance();

    expect($first)->toBeGreaterThan(0);

    foreach ([2, 2] as $quantity) {
        $this->actingAs($this->cashier, 'staff')
            ->put(route('admin.orders.update', $order), [
                'order_type' => 'takeaway',
                'items' => [['menu_item_id' => $this->item->id, 'quantity' => $quantity]],
            ])
            ->assertSessionHasNoErrors();
    }

    expect($balance())->toBe($first);
});
