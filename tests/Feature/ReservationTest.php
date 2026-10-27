<?php

declare(strict_types=1);

use App\Actions\Sales\CreateOrderAction;
use App\Enums\OrderType;
use App\Jobs\SendSmsJob;
use App\Models\Branch;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\RestaurantTable;
use App\Models\Shift;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UnitSeeder;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    $this->seed([ChartOfAccountsSeeder::class, RoleSeeder::class, UnitSeeder::class]);

    $this->branch = Branch::factory()->create(['code' => 'BR01']);
    $this->table = RestaurantTable::factory()->create(['branch_id' => $this->branch->id, 'name' => 'T3', 'seats' => 4]);
    $this->manager = User::factory()->role('branch-manager', $this->branch->id)->create();
    $this->kitchen = User::factory()->role('kitchen-manager', $this->branch->id)->create();
});

function reservationPayload(RestaurantTable $table, array $overrides = []): array
{
    return $overrides + [
        'guest_name' => 'Karim',
        'guest_mobile' => '01711000000',
        'party_size' => 4,
        'table_ids' => [$table->id],
        'starts_at' => now()->addHours(2)->toIso8601String(),
        'ends_at' => now()->addHours(3)->toIso8601String(),
        'note' => 'Birthday',
    ];
}

function makeReservation(RestaurantTable $table, array $overrides = [], ?Branch $branch = null): Reservation
{
    $reservation = Reservation::query()->create($overrides + [
        'branch_id' => ($branch ?? $table->branch)->id,
        'guest_name' => 'Karim',
        'guest_mobile' => '01711000000',
        'party_size' => 4,
        'starts_at' => now()->addHours(2),
        'ends_at' => now()->addHours(3),
    ]);

    $reservation->tables()->attach($table->id, ['position' => 0]);

    return $reservation;
}

it('books a table and lists the booking for the day', function (): void {
    $this->actingAs($this->manager, 'staff')->post(route('admin.reservations.store'), reservationPayload($this->table))->assertRedirect();

    $reservation = Reservation::query()->firstOrFail();

    expect($reservation->status)->toBe('booked')->and($reservation->branch_id)->toBe($this->branch->id);

    $this->actingAs($this->manager, 'staff')
        ->get(route('admin.reservations.index', ['from' => now()->startOfDay()->toIso8601String(), 'to' => now()->endOfDay()->toIso8601String()]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/reservations/Index')
            ->has('reservations', 1)
            ->where('reservations.0.guest_name', 'Karim')
            ->where('reservations.0.tables.0.name', 'T3')
            ->where('reservations.0.booking_no', Reservation::query()->value('booking_no'))
            ->where('canManage', true));
});

it('stores the exact moment the browser sends, whatever its clock', function (): void {
    $from = now()->addHour()->startOfMinute();
    $until = now()->addHours(3)->startOfMinute();

    $this->actingAs($this->manager, 'staff')->post(route('admin.reservations.store'), reservationPayload($this->table, [
        'starts_at' => $from->copy()->setTimezone('Asia/Dhaka')->toIso8601String(),
        'ends_at' => $until->copy()->setTimezone('Asia/Dhaka')->toIso8601String(),
    ]))->assertRedirect();

    $reservation = Reservation::query()->firstOrFail();

    expect($reservation->starts_at->equalTo($from))->toBeTrue()->and($reservation->ends_at->equalTo($until))->toBeTrue();
});

it('refuses a booking that clashes with another one on the same table', function (): void {
    $this->actingAs($this->manager, 'staff')->post(route('admin.reservations.store'), reservationPayload($this->table))->assertRedirect();

    $this->actingAs($this->manager, 'staff')->post(route('admin.reservations.store'), reservationPayload($this->table, [
        'guest_name' => 'Rafi',
        'starts_at' => now()->addHours(2)->addMinutes(30)->toIso8601String(),
        'ends_at' => now()->addHours(4)->toIso8601String(),
    ]))->assertSessionHasErrors('table_ids');

    // Back to back is fine, and so is another table at the same time.
    $this->actingAs($this->manager, 'staff')->post(route('admin.reservations.store'), reservationPayload($this->table, [
        'guest_name' => 'Rafi',
        'starts_at' => now()->addHours(3)->toIso8601String(),
        'ends_at' => now()->addHours(4)->toIso8601String(),
    ]))->assertSessionHasNoErrors();

    $other = RestaurantTable::factory()->create(['branch_id' => $this->branch->id]);

    $this->actingAs($this->manager, 'staff')->post(route('admin.reservations.store'), reservationPayload($other))->assertSessionHasNoErrors();

    expect(Reservation::query()->count())->toBe(3);
});

it('validates the booking fields', function (): void {
    $otherBranch = Branch::factory()->create(['code' => 'BR02']);
    $foreign = RestaurantTable::factory()->create(['branch_id' => $otherBranch->id]);

    $post = fn (array $overrides) => $this->actingAs($this->manager, 'staff')->post(route('admin.reservations.store'), reservationPayload($this->table, $overrides));

    $post(['guest_name' => ''])->assertSessionHasErrors('guest_name');
    $post(['party_size' => 0])->assertSessionHasErrors('party_size');
    $post(['table_ids' => [$foreign->id]])->assertSessionHasErrors('table_ids.0');
    $post(['table_ids' => []])->assertSessionHasErrors('table_ids');
    $post(['ends_at' => now()->addHour()->toIso8601String(), 'starts_at' => now()->addHours(2)->toIso8601String()])->assertSessionHasErrors('ends_at');
    $post(['starts_at' => now()->addHour()->toIso8601String(), 'ends_at' => now()->addHours(20)->toIso8601String()])->assertSessionHasErrors('ends_at');

    expect(Reservation::query()->count())->toBe(0);
});

it('moves a booking through seat and complete, and releases the table on cancel or no show', function (): void {
    $this->actingAs($this->manager, 'staff')->post(route('admin.reservations.store'), reservationPayload($this->table, [
        'starts_at' => now()->subMinutes(5)->toIso8601String(),
    ]))->assertRedirect();

    $reservation = Reservation::query()->firstOrFail();

    expect(Reservation::holdingNow($this->table->id))->not->toBeNull();

    $this->actingAs($this->manager, 'staff')->post(route('admin.reservations.transition', [$reservation, 'seat']))->assertRedirect();
    expect($reservation->fresh()->status)->toBe('seated')->and(Reservation::holdingNow($this->table->id))->toBeNull();

    $this->actingAs($this->manager, 'staff')->post(route('admin.reservations.transition', [$reservation, 'complete']))->assertRedirect();
    expect($reservation->fresh()->status)->toBe('completed');

    // A finished booking cannot be reopened or cancelled.
    $this->actingAs($this->manager, 'staff')->post(route('admin.reservations.transition', [$reservation, 'cancel']))->assertSessionHas('error');

    foreach (['cancel' => 'cancelled', 'no-show' => 'no_show'] as $action => $status) {
        $booking = makeReservation($this->table, ['starts_at' => now()->subMinutes(5), 'ends_at' => now()->addHour()]);

        $this->actingAs($this->manager, 'staff')->post(route('admin.reservations.transition', [$booking, $action]))->assertRedirect();

        expect($booking->fresh()->status)->toBe($status)->and(Reservation::holdingNow($this->table->id))->toBeNull();
    }
});

it('marks a booking seated when staff ring up an order on its table', function (): void {
    $reservation = makeReservation($this->table, [
        'starts_at' => now()->addMinutes(20),
        'ends_at' => now()->addHours(2),
    ]);

    $cashier = User::factory()->role('cashier', $this->branch->id)->create();
    $shift = Shift::query()->create(['branch_id' => $this->branch->id, 'user_id' => $cashier->id, 'terminal_code' => 'T01', 'opening_cash' => 0, 'opened_at' => now()]);
    $item = MenuItem::factory()->create(['branch_id' => $this->branch->id, 'menu_category_id' => MenuCategory::factory()->create(['branch_id' => $this->branch->id])->id]);

    app(CreateOrderAction::class)->execute(
        branch: $this->branch,
        type: OrderType::DineIn,
        lines: [['menu_item_id' => $item->id, 'quantity' => 1]],
        cashier: $cashier,
        shift: $shift,
        tableId: $this->table->id,
        terminalCode: 'T01',
    );

    expect($reservation->fresh()->status)->toBe('seated');
});

it('shows the next booking on the POS table list', function (): void {
    makeReservation($this->table, [
        'starts_at' => now()->addMinutes(30),
        'ends_at' => now()->addHours(2),
    ]);

    $cashier = User::factory()->role('cashier', $this->branch->id)->create();

    $this->actingAs($cashier, 'staff')->get('/admin/pos')->assertInertia(fn ($page) => $page
        ->where('tables.0.name', 'T3')
        ->where('tables.0.booking.guest_name', 'Karim'));
});

it('lets roles without reservation access nowhere near it', function (): void {
    $this->actingAs($this->kitchen, 'staff')->get(route('admin.reservations.index'))->assertForbidden();
    $this->actingAs($this->kitchen, 'staff')->post(route('admin.reservations.store'), reservationPayload($this->table))->assertForbidden();

    $otherBranch = Branch::factory()->create(['code' => 'BR02']);
    $foreignTable = RestaurantTable::factory()->create(['branch_id' => $otherBranch->id]);
    $foreign = makeReservation($foreignTable, [], $otherBranch);

    $this->actingAs($this->manager, 'staff')->post(route('admin.reservations.transition', [$foreign, 'seat']))->assertNotFound();
});

function menuDish($test, string $price = '200.00'): MenuItem
{
    $test->category ??= MenuCategory::factory()->create(['branch_id' => $test->branch->id]);

    return MenuItem::factory()->create(['branch_id' => $test->branch->id, 'menu_category_id' => $test->category->id, 'base_price' => $price]);
}

function bookWith($test, array $overrides = []): Reservation
{
    $test->actingAs($test->manager, 'staff')->post(route('admin.reservations.store'), reservationPayload($test->table, $overrides))->assertSessionHasNoErrors();

    return Reservation::query()->latest('id')->firstOrFail();
}

it('books several tables for one party and holds all of them', function (): void {
    $second = RestaurantTable::factory()->create(['branch_id' => $this->branch->id, 'name' => 'T4', 'seats' => 6]);

    $reservation = bookWith($this, ['table_ids' => [$second->id, $this->table->id], 'party_size' => 9]);

    expect($reservation->tables->pluck('name')->all())->toBe(['T4', 'T3'])
        ->and($reservation->tables->sum('seats'))->toBe(10);

    // Another booking cannot take either table at that time.
    foreach ([$this->table, $second] as $table) {
        $this->actingAs($this->manager, 'staff')->post(route('admin.reservations.store'), reservationPayload($table, ['guest_name' => 'Rafi']))->assertSessionHasErrors('table_ids');
    }

    $this->actingAs($this->manager, 'staff')
        ->get(route('admin.reservations.index', ['from' => now()->startOfDay()->toIso8601String(), 'to' => now()->endOfDay()->toIso8601String()]))
        ->assertInertia(fn ($page) => $page->where('reservations.0.seats', 10)->where('reservations.0.tables.0.name', 'T4'));
});

it('closes the QR code of every table of a running booking until the party is seated', function (): void {
    $second = RestaurantTable::factory()->create(['branch_id' => $this->branch->id, 'name' => 'T4']);

    $reservation = makeReservation($this->table, ['starts_at' => now()->subMinutes(5), 'ends_at' => now()->addHour()]);
    $reservation->tables()->attach($second->id, ['position' => 1]);

    expect(Reservation::holdingNow($this->table->id))->not->toBeNull()
        ->and(Reservation::holdingNow($second->id))->not->toBeNull();

    $this->actingAs($this->manager, 'staff')->post(route('admin.reservations.transition', [$reservation, 'seat']))->assertRedirect();

    expect(Reservation::holdingNow($second->id))->toBeNull();
});

it('prices a pre-order from the menu and sends it to the kitchen when the guests sit down', function (): void {
    $dish = menuDish($this);
    $reservation = bookWith($this, ['items' => [['menu_item_id' => $dish->id, 'quantity' => 3]]]);

    expect((float) $reservation->preorder_total)->toBe(600.0)
        ->and($reservation->preorder[0]['name'])->toBe($dish->name)
        ->and($reservation->preorder[0]['line_total'])->toBe('600.00');

    $this->actingAs($this->manager, 'staff')->post(route('admin.reservations.transition', [$reservation, 'seat']))
        ->assertRedirect()
        ->assertSessionHas('success', fn (string $message) => str_contains($message, '-RSV-'));

    $order = Order::query()->firstOrFail();

    expect($order->restaurant_table_id)->toBe($this->table->id)
        ->and($order->order_type)->toBe(OrderType::DineIn)
        ->and($order->items->sum('quantity'))->toBe(3)
        ->and($order->note)->toContain($reservation->booking_no)
        ->and($order->invoice_no)->toContain('-RSV-')
        ->and($this->table->fresh()->status)->toBe('occupied')
        ->and($reservation->fresh()->status)->toBe('seated');
});

it('refuses a pre-order dish that is sold out or from another branch', function (): void {
    $soldOut = menuDish($this);
    $soldOut->update(['is_available' => false]);

    $this->actingAs($this->manager, 'staff')->post(route('admin.reservations.store'), reservationPayload($this->table, [
        'items' => [['menu_item_id' => $soldOut->id, 'quantity' => 1]],
    ]))->assertSessionHasErrors('items.0.menu_item_id');

    expect(Reservation::query()->count())->toBe(0);
});

it('takes booking money with the booking and needs a TrxID for a wallet', function (): void {
    $this->actingAs($this->manager, 'staff')->post(route('admin.reservations.store'), reservationPayload($this->table, [
        'deposit_amount' => 1000, 'deposit_method' => 'bkash',
    ]))->assertSessionHasErrors('deposit_reference');

    expect(Reservation::query()->count())->toBe(0);

    $reservation = bookWith($this, ['deposit_amount' => 1000, 'deposit_method' => 'bkash', 'deposit_reference' => 'TRX123']);

    expect($reservation->deposit_status)->toBe('held')
        ->and((float) $reservation->deposit_amount)->toBe(1000.0)
        ->and($reservation->deposit_reference)->toBe('TRX123')
        ->and($reservation->depositBalance())->toBe(1000.0);
});

it('takes booking money later, once', function (): void {
    $reservation = bookWith($this);

    $this->actingAs($this->manager, 'staff')->post(route('admin.reservations.deposit', $reservation), ['deposit_amount' => 500, 'deposit_method' => 'cash'])->assertRedirect();

    expect($reservation->fresh()->deposit_status)->toBe('held');

    $this->actingAs($this->manager, 'staff')->post(route('admin.reservations.deposit', $reservation), ['deposit_amount' => 500, 'deposit_method' => 'cash'])->assertSessionHasErrors('deposit_amount');
});

it('takes the booking money off the bill and leaves only the balance to pay', function (): void {
    $dish = menuDish($this);
    $reservation = bookWith($this, [
        'items' => [['menu_item_id' => $dish->id, 'quantity' => 2]],
        'deposit_amount' => 300, 'deposit_method' => 'cash',
    ]);

    $this->actingAs($this->manager, 'staff')->post(route('admin.reservations.transition', [$reservation, 'seat']))->assertRedirect();

    $order = Order::query()->firstOrFail();
    $advance = $order->payments()->where('is_advance', true)->firstOrFail();

    expect((float) $advance->amount)->toBe(300.0)
        ->and($advance->method)->toBe('cash')
        ->and($order->balanceDue())->toBe(round((float) $order->total - 300, 2))
        ->and($order->fresh()->status->value)->toBe('pending')
        ->and($reservation->fresh()->deposit_status)->toBe('applied')
        ->and($reservation->fresh()->deposit_order_id)->toBe($order->id);

    // The running orders on the POS still list it, with the balance.
    $cashier = User::factory()->role('cashier', $this->branch->id)->create();

    $this->actingAs($cashier, 'staff')->get('/admin/pos')->assertInertia(fn ($page) => $page
        ->where('openOrders.0.invoice_no', $order->invoice_no)
        ->where('openOrders.0.advance', 300)
        ->where('openOrders.0.balance', fn ($balance) => abs($balance - $order->balanceDue()) < 0.01));

    // Paying the whole bill is no longer enough to ask for: only the balance is due.
    $this->actingAs($cashier, 'staff')->post(route('admin.orders.pay', $order), ['tendered' => $order->balanceDue() - 1])->assertSessionHasErrors('tendered');
    $this->actingAs($cashier, 'staff')->post(route('admin.orders.pay', $order), ['tendered' => $order->balanceDue() + 10])->assertRedirect();

    $order->refresh();

    expect($order->status->value)->toBe('paid')
        ->and($order->amountPaid())->toBe((float) $order->total)
        ->and($order->payments()->where('is_advance', false)->firstOrFail()->change_due)->toBe('10.00');
});

it('settles a bill fully with booking money and keeps the rest of the advance', function (): void {
    $dish = menuDish($this, '100.00');
    $reservation = bookWith($this, [
        'items' => [['menu_item_id' => $dish->id, 'quantity' => 1]],
        'deposit_amount' => 5000, 'deposit_method' => 'cash',
    ]);

    $this->actingAs($this->manager, 'staff')->post(route('admin.reservations.transition', [$reservation, 'seat']))->assertRedirect();

    $order = Order::query()->firstOrFail()->refresh();
    $reservation->refresh();

    expect($order->status->value)->toBe('paid')
        ->and((float) $reservation->deposit_applied)->toBe((float) $order->total)
        ->and($reservation->deposit_status)->toBe('held')
        ->and($reservation->depositBalance())->toBe(round(5000 - (float) $order->total, 2));

    $this->actingAs($this->manager, 'staff')->post(route('admin.reservations.deposit.close', [$reservation, 'refund']))->assertRedirect();

    expect($reservation->fresh()->deposit_status)->toBe('refunded');
});

it('applies the held booking money to the first order rung up on the table', function (): void {
    $dish = menuDish($this);
    $reservation = bookWith($this, ['deposit_amount' => 150, 'deposit_method' => 'cash', 'starts_at' => now()->subMinutes(5)->toIso8601String(), 'ends_at' => now()->addHours(2)->toIso8601String()]);

    $cashier = User::factory()->role('cashier', $this->branch->id)->create();
    $shift = Shift::query()->create(['branch_id' => $this->branch->id, 'user_id' => $cashier->id, 'terminal_code' => 'T01', 'opening_cash' => 0, 'opened_at' => now()]);

    $order = app(CreateOrderAction::class)->execute(
        branch: $this->branch,
        type: OrderType::DineIn,
        lines: [['menu_item_id' => $dish->id, 'quantity' => 1]],
        cashier: $cashier,
        shift: $shift,
        tableId: $this->table->id,
        terminalCode: 'T01',
    );

    expect($order->payments()->where('is_advance', true)->sum('amount'))->toEqual(150)
        ->and($reservation->fresh()->status)->toBe('seated')
        ->and($reservation->fresh()->deposit_status)->toBe('applied');

    // A second order at the same table does not get the money twice.
    $second = app(CreateOrderAction::class)->execute(
        branch: $this->branch,
        type: OrderType::DineIn,
        lines: [['menu_item_id' => $dish->id, 'quantity' => 1]],
        cashier: $cashier,
        shift: $shift,
        tableId: $this->table->id,
        terminalCode: 'T01',
    );

    expect($second->payments()->count())->toBe(0);
});

it('does not let an order with booking money on it be revised', function (): void {
    $dish = menuDish($this);
    $reservation = bookWith($this, ['items' => [['menu_item_id' => $dish->id, 'quantity' => 1]], 'deposit_amount' => 50, 'deposit_method' => 'cash']);

    $this->actingAs($this->manager, 'staff')->post(route('admin.reservations.transition', [$reservation, 'seat']))->assertRedirect();

    $order = Order::query()->firstOrFail();

    $this->actingAs($this->manager, 'staff')->put(route('admin.orders.update', $order), [
        'order_type' => 'dine_in',
        'restaurant_table_id' => $this->table->id,
        'items' => [['menu_item_id' => $dish->id, 'quantity' => 4]],
    ])->assertSessionHas('error', __('orders.advance_locked'));

    expect($order->fresh()->items->sum('quantity'))->toBe(1);
});

it('refunds or keeps the booking money that is left, once', function (string $outcome, string $status): void {
    $reservation = bookWith($this, ['deposit_amount' => 800, 'deposit_method' => 'cash']);

    $this->actingAs($this->manager, 'staff')->post(route('admin.reservations.transition', [$reservation, 'cancel']))->assertRedirect();
    $this->actingAs($this->manager, 'staff')->post(route('admin.reservations.deposit.close', [$reservation, $outcome]))->assertRedirect();

    expect($reservation->fresh()->deposit_status)->toBe($status)->and($reservation->fresh()->deposit_closed_at)->not->toBeNull();

    $this->actingAs($this->manager, 'staff')->post(route('admin.reservations.deposit.close', [$reservation, $outcome]))->assertSessionHasErrors('deposit_amount');
})->with([
    'refund' => ['refund', 'refunded'],
    'keep' => ['forfeit', 'forfeited'],
]);

it('prints a confirmation slip with the booking number, tables, pre-order and booking money', function (): void {
    $second = RestaurantTable::factory()->create(['branch_id' => $this->branch->id, 'name' => 'T4']);
    $dish = menuDish($this);
    $reservation = bookWith($this, [
        'table_ids' => [$this->table->id, $second->id],
        'items' => [['menu_item_id' => $dish->id, 'quantity' => 2]],
        'deposit_amount' => 700, 'deposit_method' => 'bkash', 'deposit_reference' => 'TRX9',
    ]);

    $this->actingAs($this->manager, 'staff')->get(route('admin.reservations.confirmation', $reservation))
        ->assertOk()
        ->assertSee($reservation->booking_no)
        ->assertSee('T3, T4')
        ->assertSee('Karim')
        ->assertSee($dish->name)
        ->assertSee('700.00')
        ->assertSee('TRX9')
        ->assertSee('bKash');

    $this->actingAs($this->kitchen, 'staff')->get(route('admin.reservations.confirmation', $reservation))->assertForbidden();
});

it('texts the guest the booking confirmation', function (): void {
    Queue::fake();

    $reservation = bookWith($this);

    Queue::assertPushed(SendSmsJob::class, fn (SendSmsJob $job) => str_contains($job->message, $reservation->booking_no) && str_contains($job->message, 'T3'));
});

it('gives each booking its own readable number', function (): void {
    $first = bookWith($this);
    $second = bookWith($this, ['starts_at' => now()->addHours(5)->toIso8601String(), 'ends_at' => now()->addHours(6)->toIso8601String()]);

    expect($first->booking_no)->toMatch('/^RSV-\d{4}-[A-Z2-9]{4}$/')->and($second->booking_no)->not->toBe($first->booking_no);
});
