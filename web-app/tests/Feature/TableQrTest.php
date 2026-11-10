<?php

declare(strict_types=1);

use App\Actions\Sales\CreateOrderAction;
use App\Enums\OrderChannel;
use App\Enums\OrderType;
use App\Http\Middleware\HandleInertiaRequests;
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
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function (): void {
    $this->seed([ChartOfAccountsSeeder::class, RoleSeeder::class, UnitSeeder::class]);

    $this->branch = Branch::factory()->create(['code' => 'BR01']);
    $this->table = RestaurantTable::factory()->create(['branch_id' => $this->branch->id, 'name' => 'T3', 'area' => 'Garden']);
    $this->manager = User::factory()->role('branch-manager', $this->branch->id)->create();
    $this->cashier = User::factory()->role('cashier', $this->branch->id)->create();

    $category = MenuCategory::factory()->create(['branch_id' => $this->branch->id]);
    $this->item = MenuItem::factory()->create(['branch_id' => $this->branch->id, 'menu_category_id' => $category->id, 'base_price' => '200.00']);

    RateLimiter::clear('table-order:'.$this->table->id);
});

function qrUrl(RestaurantTable $table): string
{
    return route('web.table', ['token' => $table->fresh()->qr_token]);
}

it('gives every table its own long secret and never sends it to the POS', function (): void {
    $other = RestaurantTable::factory()->create(['branch_id' => $this->branch->id]);

    expect($this->table->qr_token)->toHaveLength(32)
        ->and($this->table->qr_token)->not->toBe($other->qr_token)
        ->and($this->table->qr_generated_at)->not->toBeNull()
        ->and($this->table->toArray())->not->toHaveKey('qr_token');

    $this->actingAs($this->cashier, 'staff')->get('/admin/pos')->assertInertia(fn ($page) => $page
        ->missing('tables.0.qr_token'));
});

it('opens the menu book for the table behind a valid code', function (): void {
    $this->get(qrUrl($this->table))
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertInertia(fn ($page) => $page
            ->component('web/TableMenu')
            ->where('table.name', 'T3')
            ->where('table.area', 'Garden')
            ->has('categories.0.items', 1));
});

it('turns away codes that are wrong, old or made up', function (string $path): void {
    $this->get($path)->assertStatus(410)->assertInertia(fn ($page) => $page->component('web/TableInvalid'));
})->with([
    'wrong secret' => fn () => '/t/'.str_repeat('a', 32),
    'table name' => fn () => '/q/T3',
    'branch and table name' => fn () => '/q/br01/T3',
]);

it('stops an old printed code the moment a new one is issued', function (): void {
    $oldUrl = qrUrl($this->table);

    $this->actingAs($this->manager, 'staff')
        ->post(route('admin.tables.qr.regenerate', $this->table))
        ->assertRedirect();

    expect(qrUrl($this->table))->not->toBe($oldUrl);

    $this->get($oldUrl)->assertStatus(410);
    $this->get(qrUrl($this->table))->assertOk();
});

it('refuses an order sent with an old code', function (): void {
    $oldToken = $this->table->qr_token;

    $this->table->rotateQrToken();

    $this->post(route('web.table.order', ['token' => $oldToken]), [
        'items' => [['menu_item_id' => $this->item->id, 'quantity' => 1]],
    ])->assertStatus(410);

    expect(Order::query()->count())->toBe(0);
});

it('lets only users who manage tables issue codes', function (): void {
    $this->actingAs($this->cashier, 'staff')
        ->post(route('admin.tables.qr.regenerate', $this->table))
        ->assertForbidden();

    $otherBranch = Branch::factory()->create(['code' => 'BR02']);
    $foreign = RestaurantTable::factory()->create(['branch_id' => $otherBranch->id]);

    $this->actingAs($this->manager, 'staff')
        ->post(route('admin.tables.qr.regenerate', $foreign))
        ->assertNotFound();
});

it('renews every code of the branch at once', function (): void {
    $second = RestaurantTable::factory()->create(['branch_id' => $this->branch->id]);
    $before = [$this->table->qr_token, $second->qr_token];

    $this->actingAs($this->manager, 'staff')->post(route('admin.tables.qr.regenerate-all'))->assertRedirect();

    expect($this->table->fresh()->qr_token)->not->toBe($before[0])
        ->and($second->fresh()->qr_token)->not->toBe($before[1]);
});

it('sends a guest order straight to the kitchen with the table already chosen', function (): void {
    Event::fake();

    $this->post(route('web.table.order', ['token' => $this->table->qr_token]), [
        'items' => [['menu_item_id' => $this->item->id, 'quantity' => 2]],
        'guest_name' => 'Rafi',
        'note' => 'less spicy',
    ])->assertRedirect(qrUrl($this->table))->assertSessionHas('table_order');

    $order = Order::query()->firstOrFail();

    expect($order->channel)->toBe(OrderChannel::Qr)
        ->and($order->order_type)->toBe(OrderType::DineIn)
        ->and($order->restaurant_table_id)->toBe($this->table->id)
        ->and($order->status->value)->toBe('pending')
        ->and($order->invoice_no)->toContain('-QR-')
        ->and($order->note)->toContain('Rafi')->toContain('less spicy')
        ->and($this->table->fresh()->status)->toBe('occupied');

    // The confirmation shows once on the menu page.
    $this->get(qrUrl($this->table))->assertInertia(fn ($page) => $page->where('placed.invoice_no', $order->invoice_no));
    $this->get(qrUrl($this->table))->assertInertia(fn ($page) => $page->where('placed', null));
});

it('shows the guest order on the POS with its table', function (): void {
    $payload = ['items' => [['menu_item_id' => $this->item->id, 'quantity' => 1]]];

    $this->post(route('web.table.order', ['token' => $this->table->qr_token]), $payload)->assertRedirect();
    $first = Order::query()->firstOrFail();
    $this->post(route('web.table.order', ['token' => $this->table->qr_token]), $payload)->assertRedirect();

    $this->actingAs($this->cashier, 'staff')->get('/admin/pos')->assertInertia(fn ($page) => $page
        ->where('openOrders.0.restaurant_table_id', $this->table->id)
        ->where('tables.0.status', 'occupied'));

    $this->actingAs($this->cashier, 'staff')->getJson('/admin/orders/incoming?after='.$first->id)
        ->assertOk()
        ->assertJsonPath('orders.0.channel', 'qr');
});

it('limits how fast one table can send orders', function (): void {
    $payload = ['items' => [['menu_item_id' => $this->item->id, 'quantity' => 1]]];

    foreach (range(1, 6) as $ignored) {
        $this->post(route('web.table.order', ['token' => $this->table->qr_token]), $payload)->assertRedirect();
    }

    $this->post(route('web.table.order', ['token' => $this->table->qr_token]), $payload)->assertSessionHasErrors('order');

    expect(Order::query()->count())->toBe(6);
});

it('rejects dishes from another branch or ones that are sold out', function (): void {
    $otherBranch = Branch::factory()->create(['code' => 'BR02']);
    $foreignItem = MenuItem::factory()->create([
        'branch_id' => $otherBranch->id,
        'menu_category_id' => MenuCategory::factory()->create(['branch_id' => $otherBranch->id])->id,
    ]);
    $soldOut = MenuItem::factory()->create([
        'branch_id' => $this->branch->id,
        'menu_category_id' => $this->item->menu_category_id,
        'is_available' => false,
    ]);

    foreach ([$foreignItem, $soldOut] as $item) {
        $this->post(route('web.table.order', ['token' => $this->table->qr_token]), [
            'items' => [['menu_item_id' => $item->id, 'quantity' => 1]],
        ])->assertSessionHasErrors('items.0.menu_item_id');
    }

    expect(Order::query()->count())->toBe(0);
});

it('does not let the website checkout pick a table', function (): void {
    $this->post('/checkout', [
        'order_type' => 'dine_in',
        'payment_method' => 'cod',
        'items' => [['menu_item_id' => $this->item->id, 'quantity' => 1]],
    ])->assertSessionHasErrors('order_type');
});

it('also answers the in-page requests the menu book makes', function (): void {
    $headers = ['X-Inertia' => 'true', 'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()), 'X-Requested-With' => 'XMLHttpRequest'];

    $this->withHeaders($headers)->get(qrUrl($this->table))->assertOk()->assertJsonPath('component', 'web/TableMenu');

    $this->withHeaders($headers)
        ->post(route('web.table.order', ['token' => $this->table->qr_token]), ['items' => [['menu_item_id' => $this->item->id, 'quantity' => 1]]])
        ->assertRedirect(qrUrl($this->table));

    $this->withHeaders($headers)->get('/t/'.str_repeat('b', 32))->assertStatus(410);
});

function qrPayload(MenuItem $item): array
{
    return ['items' => [['menu_item_id' => $item->id, 'quantity' => 1]]];
}

it('keeps a table open to more QR orders but closes it once someone else is seated', function (): void {
    // First guest orders by QR: the table becomes occupied, and more phones at the table can still order.
    $this->post(route('web.table.order', ['token' => $this->table->qr_token]), qrPayload($this->item))->assertRedirect();

    expect($this->table->fresh()->status)->toBe('occupied');

    $this->get(qrUrl($this->table))->assertInertia(fn ($page) => $page->where('ordering.allowed', true));
    $this->post(route('web.table.order', ['token' => $this->table->qr_token]), qrPayload($this->item))->assertRedirect();

    // A waiter then rings up a normal order on the same table: the QR code stops taking orders.
    $shift = Shift::query()->create(['branch_id' => $this->branch->id, 'user_id' => $this->cashier->id, 'terminal_code' => 'T01', 'opening_cash' => 0, 'opened_at' => now()]);

    app(CreateOrderAction::class)->execute(
        branch: $this->branch,
        type: OrderType::DineIn,
        lines: [['menu_item_id' => $this->item->id, 'quantity' => 1]],
        cashier: $this->cashier,
        shift: $shift,
        tableId: $this->table->id,
        terminalCode: 'T01',
    );

    $this->get(qrUrl($this->table))->assertInertia(fn ($page) => $page
        ->where('ordering.allowed', false)
        ->where('ordering.reason', 'occupied'));

    $count = Order::query()->count();

    $this->post(route('web.table.order', ['token' => $this->table->qr_token]), qrPayload($this->item))->assertSessionHasErrors('order');

    expect(Order::query()->count())->toBe($count);
});

it('treats a table marked occupied with nothing open as taken, and opens it once freed', function (): void {
    $this->table->update(['status' => 'occupied']);

    $this->get(qrUrl($this->table))->assertInertia(fn ($page) => $page->where('ordering.reason', 'occupied'));

    $this->table->update(['status' => 'free']);

    $this->get(qrUrl($this->table))->assertInertia(fn ($page) => $page->where('ordering.allowed', true));
});

function booking(RestaurantTable $table, string $status, int $startsInMinutes, int $lastsMinutes, array $extra = []): Reservation
{
    $reservation = Reservation::query()->create($extra + [
        'branch_id' => $table->branch_id,
        'guest_name' => 'Karim',
        'party_size' => 4,
        'starts_at' => now()->addMinutes($startsInMinutes),
        'ends_at' => now()->addMinutes($startsInMinutes + $lastsMinutes),
        'status' => $status,
    ]);

    $reservation->tables()->attach($table->id, ['position' => 0]);

    return $reservation;
}

it('closes the table for QR orders while a booking is waiting for its guests', function (): void {
    booking($this->table, Reservation::BOOKED, -10, 90);

    $this->get(qrUrl($this->table))->assertOk()->assertInertia(fn ($page) => $page
        ->where('ordering.allowed', false)
        ->where('ordering.reason', 'reserved')
        ->has('ordering.until')
        ->has('categories'));

    $this->post(route('web.table.order', ['token' => $this->table->qr_token]), qrPayload($this->item))->assertSessionHasErrors('order');

    expect(Order::query()->count())->toBe(0);
});

it('only blocks while a booking is running and its guests have not been seated', function (): void {
    $booked = booking($this->table, Reservation::BOOKED, 120, 60);

    // Later today: not yet.
    $this->get(qrUrl($this->table))->assertInertia(fn ($page) => $page->where('ordering.allowed', true));

    // Over, cancelled or no show: free.
    $booked->update(['starts_at' => now()->subHours(3), 'ends_at' => now()->subHour()]);
    $this->get(qrUrl($this->table))->assertInertia(fn ($page) => $page->where('ordering.allowed', true));

    foreach ([Reservation::CANCELLED, Reservation::NO_SHOW, Reservation::COMPLETED] as $status) {
        $booked->update(['starts_at' => now()->subMinutes(10), 'ends_at' => now()->addHour(), 'status' => $status]);
        $this->get(qrUrl($this->table))->assertInertia(fn ($page) => $page->where('ordering.allowed', true));
    }

    // Seated guests are the ones sitting there, so the table opens for them.
    $booked->update(['status' => Reservation::SEATED]);
    $this->post(route('web.table.order', ['token' => $this->table->qr_token]), qrPayload($this->item))->assertRedirect();
});

it('does not take the booking from another table into account', function (): void {
    $other = RestaurantTable::factory()->create(['branch_id' => $this->branch->id]);
    booking($other, Reservation::BOOKED, -10, 90);

    $this->get(qrUrl($this->table))->assertInertia(fn ($page) => $page->where('ordering.allowed', true));
});
