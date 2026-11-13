<?php

declare(strict_types=1);

use App\Jobs\SendSmsJob;
use App\Models\Branch;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Preorder;
use App\Models\Reservation;
use App\Models\RestaurantTable;
use App\Models\User;
use Database\Seeders\ArmansKitchenQasrAlMandiSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);

    $this->branch = Branch::factory()->create(['code' => 'BR01']);
    $this->manager = User::factory()->role('branch-manager', $this->branch->id)->create();
    $this->table = RestaurantTable::factory()->create(['branch_id' => $this->branch->id, 'name' => 'T3']);
    $this->category = MenuCategory::factory()->create(['branch_id' => $this->branch->id]);
    $this->item = MenuItem::factory()->create([
        'branch_id' => $this->branch->id,
        'menu_category_id' => $this->category->id,
        'base_price' => 200,
        'is_available' => true,
    ]);
});

function preorderPayload(MenuItem $item, array $overrides = []): array
{
    return $overrides + ['items' => [['menu_item_id' => $item->id, 'quantity' => 2]]];
}

it('shows the menu book with the branch menu and table choices to staff', function (): void {
    $response = $this->actingAs($this->manager, 'staff')->get(route('admin.preorders.index'));

    $response->assertOk();

    $props = $response->viewData('page')['props'];

    expect($props['categories'])->toHaveCount(1)
        ->and($props['tables'])->toHaveCount(1)
        ->and($props['shareUrl'])->toBe(route('web.preorder', ['branchCode' => 'br01']));
});

it('takes a pre-order with no guest details and no table', function (): void {
    Queue::fake();

    $this->actingAs($this->manager, 'staff')
        ->post(route('admin.preorders.store'), preorderPayload($this->item))
        ->assertRedirect()
        ->assertSessionHas('preorder_placed');

    $preorder = Preorder::query()->firstOrFail();

    expect($preorder->status)->toBe(Preorder::PENDING)
        ->and($preorder->source)->toBe(Preorder::SOURCE_STAFF)
        ->and($preorder->customer_name)->toBeNull()
        ->and($preorder->restaurant_table_id)->toBeNull()
        ->and((float) $preorder->total)->toBe(400.0)
        ->and($preorder->order_no)->toStartWith('PRE-');

    Queue::assertNotPushed(SendSmsJob::class);
});

it('keeps the optional table, name, mobile and time and texts the guest a copy', function (): void {
    Queue::fake();

    $this->actingAs($this->manager, 'staff')->post(route('admin.preorders.store'), preorderPayload($this->item, [
        'customer_name' => 'Karim',
        'customer_mobile' => '01711000000',
        'party_size' => 4,
        'expected_at' => now()->addDay()->toIso8601String(),
        'restaurant_table_id' => $this->table->id,
        'note' => 'Birthday',
    ]))->assertRedirect();

    $preorder = Preorder::query()->firstOrFail();

    expect($preorder->customer_name)->toBe('Karim')
        ->and($preorder->restaurant_table_id)->toBe($this->table->id)
        ->and($preorder->sms_sent_at)->not->toBeNull();

    Queue::assertPushed(SendSmsJob::class, fn (SendSmsJob $job): bool => $job->mobile === '8801711000000'
        && $job->feature === 'preorder_confirmed'
        && str_contains($job->message, $preorder->order_no)
        && str_contains($job->message, '2 x'));
});

it('refuses an empty pre-order and a table from another branch', function (): void {
    $other = RestaurantTable::factory()->create(['branch_id' => Branch::factory()->create()->id]);

    $this->actingAs($this->manager, 'staff')->post(route('admin.preorders.store'), ['items' => []])->assertSessionHasErrors('items');
    $this->actingAs($this->manager, 'staff')->post(route('admin.preorders.store'), preorderPayload($this->item, ['restaurant_table_id' => $other->id]))->assertSessionHasErrors('restaurant_table_id');

    expect(Preorder::query()->count())->toBe(0);
});

it('lets anyone browse the public book and send a pre-order without signing in', function (): void {
    Queue::fake();

    $this->get(route('web.preorder', ['branchCode' => 'br01']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('web/PreorderBook')->has('categories', 1));

    $this->post(route('web.preorder.store', ['branchCode' => 'br01']), preorderPayload($this->item, ['customer_name' => 'Rina']))
        ->assertRedirect()
        ->assertSessionHas('preorder_placed');

    expect(Preorder::query()->firstOrFail()->source)->toBe(Preorder::SOURCE_PUBLIC);
});

it('does not let the public link pick a table', function (): void {
    $this->post(route('web.preorder.store', ['branchCode' => 'br01']), preorderPayload($this->item, ['restaurant_table_id' => $this->table->id]))
        ->assertSessionHasErrors('restaurant_table_id');
});

it('limits how many pre-orders one device can send in an hour', function (): void {
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->post(route('web.preorder.store', ['branchCode' => 'br01']), preorderPayload($this->item))->assertSessionHasNoErrors();
    }

    $this->post(route('web.preorder.store', ['branchCode' => 'br01']), preorderPayload($this->item))->assertSessionHasErrors('items');
});

it('opens the printable copy by its secret link and by staff login', function (): void {
    $preorder = Preorder::query()->create([
        'branch_id' => $this->branch->id,
        'items' => [['menu_item_id' => $this->item->id, 'quantity' => 1, 'name' => 'Burger', 'name_bn' => null, 'line_total' => '200.00', 'add_ons' => []]],
        'total' => 200,
    ]);

    $this->get(route('web.preorder.slip', ['token' => $preorder->token]))->assertOk()->assertSee($preorder->order_no)->assertSee('Burger');
    $this->get(route('admin.preorders.print', $preorder))->assertRedirect();
    $this->actingAs($this->manager, 'staff')->get(route('admin.preorders.print', $preorder))->assertOk()->assertSee($preorder->order_no);
});

it('marks a pre-order served or cancelled once', function (): void {
    $preorder = Preorder::query()->create(['branch_id' => $this->branch->id, 'items' => [], 'total' => 0]);

    $this->actingAs($this->manager, 'staff')->post(route('admin.preorders.transition', [$preorder, 'complete']))->assertRedirect();
    expect($preorder->fresh()->status)->toBe(Preorder::COMPLETED);

    $this->actingAs($this->manager, 'staff')->post(route('admin.preorders.transition', [$preorder, 'cancel']))->assertSessionHas('error');
    expect($preorder->fresh()->status)->toBe(Preorder::COMPLETED);
});

it('counts waiting pre-orders on the dashboard together with booking pre-orders', function (): void {
    Preorder::query()->create(['branch_id' => $this->branch->id, 'items' => [], 'total' => 0]);
    Preorder::query()->create(['branch_id' => $this->branch->id, 'items' => [], 'total' => 0, 'status' => Preorder::CANCELLED]);

    $reservation = Reservation::query()->create([
        'branch_id' => $this->branch->id,
        'guest_name' => 'Karim',
        'party_size' => 2,
        'starts_at' => now()->addHours(2),
        'ends_at' => now()->addHours(3),
        'preorder' => [['menu_item_id' => $this->item->id, 'quantity' => 1]],
    ]);
    $reservation->tables()->attach($this->table->id, ['position' => 0]);

    $this->actingAs($this->manager, 'staff')->get('/admin')->assertInertia(fn ($page) => $page->where('preorders', 2));
});

it('keeps pre-orders of other branches out of the list', function (): void {
    $other = Branch::factory()->create();
    Preorder::query()->withoutBranchScope()->create(['branch_id' => $other->id, 'items' => [], 'total' => 0]);

    $response = $this->actingAs($this->manager, 'staff')->get(route('admin.preorders.index'));

    expect($response->viewData('page')['props']['preorders'])->toHaveCount(0);
});

it('keeps an address and an advance payment and shows the balance on the slip', function (): void {
    Queue::fake();

    $this->actingAs($this->manager, 'staff')->post(route('admin.preorders.store'), preorderPayload($this->item, [
        'address' => 'House 4, Road 2, Dhanmondi',
        'advance_amount' => 150,
    ]))->assertRedirect();

    $preorder = Preorder::query()->firstOrFail();

    expect($preorder->address)->toBe('House 4, Road 2, Dhanmondi')
        ->and((float) $preorder->advance_amount)->toBe(150.0)
        ->and($preorder->balanceDue())->toBe(250.0);

    $this->actingAs($this->manager, 'staff')->get(route('admin.preorders.print', $preorder))
        ->assertOk()
        ->assertSee('House 4, Road 2, Dhanmondi')
        ->assertSee('150.00')
        ->assertSee('250.00');
});

it('refuses an advance larger than the pre-order total', function (): void {
    $this->actingAs($this->manager, 'staff')
        ->post(route('admin.preorders.store'), preorderPayload($this->item, ['advance_amount' => 500]))
        ->assertSessionHasErrors('advance_amount');

    expect(Preorder::query()->count())->toBe(0);
});

it('lets a guest on the public link give an address but not claim an advance', function (): void {
    $this->post(route('web.preorder.store', ['branchCode' => 'br01']), preorderPayload($this->item, ['advance_amount' => 100]))
        ->assertSessionHasErrors('advance_amount');

    $this->post(route('web.preorder.store', ['branchCode' => 'br01']), preorderPayload($this->item, ['address' => 'Mirpur 10']))
        ->assertSessionHasNoErrors();

    expect(Preorder::query()->firstOrFail()->address)->toBe('Mirpur 10');
});

it('sends the waiting pre-orders with their date and time to the dashboard list', function (): void {
    Preorder::query()->create(['branch_id' => $this->branch->id, 'items' => [['quantity' => 2]], 'total' => 400, 'customer_name' => 'Rina', 'expected_at' => now()->addDay()]);
    Preorder::query()->create(['branch_id' => $this->branch->id, 'items' => [], 'total' => 0, 'status' => Preorder::CANCELLED]);

    $this->actingAs($this->manager, 'staff')->get('/admin')->assertInertia(fn ($page) => $page
        ->where('preorders', 1)
        ->has('waitingPreorders', 1)
        ->where('waitingPreorders.0.name', 'Rina')
        ->where('waitingPreorders.0.kind', 'menu_book')
        ->where('waitingPreorders.0.planned', true)
        ->where('waitingPreorders.0.items', 2));
});

it('serves the public branch menu at the short branch address used by the printed QR code', function (): void {
    $this->get('/br01/menu')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('web/PreorderBook')
            ->where('shareUrl', url('/br01/menu'))
            ->where('branch.code', 'BR01'));

    $this->get('/BR01/menu')->assertOk();
    $this->get('/nope/menu')->assertNotFound();
});

it('gives the menu page the public menu link and texts it to a guest', function (): void {
    Queue::fake();

    $this->actingAs($this->manager, 'staff')
        ->get(route('admin.menu.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('menuQr.url', url('/br01/menu')));

    $this->actingAs($this->manager, 'staff')
        ->post(route('admin.menu.qr-sms'), ['mobile' => '01712345678'])
        ->assertRedirect();

    Queue::assertPushed(SendSmsJob::class, fn (SendSmsJob $job): bool => $job->mobile === '8801712345678'
        && str_contains($job->message, url('/br01/menu'))
        && $job->feature === 'menu_link');

    $this->actingAs($this->manager, 'staff')
        ->post(route('admin.menu.qr-sms'), ['mobile' => '12345'])
        ->assertSessionHasErrors('mobile');
});

it('gives the menu book the Saudi flag of each category and the contact lines for the closing page', function (): void {
    $saudi = MenuCategory::factory()->create(['branch_id' => $this->branch->id, 'is_preorder_only' => true]);
    MenuItem::factory()->create(['branch_id' => $this->branch->id, 'menu_category_id' => $saudi->id, 'is_available' => true]);

    $this->get(route('web.preorder', ['branchCode' => 'br01']))->assertInertia(fn ($page) => $page
        ->has('categories', 2)
        ->where('categories', fn ($categories) => collect($categories)->contains(fn ($row) => $row['id'] === $saudi->id && $row['is_preorder_only'] === true))
        ->has('contact.website'));

    $this->actingAs($this->manager, 'staff')
        ->put(route('admin.menu.categories.update', $saudi), ['name' => 'Saudi', 'is_preorder_only' => false, 'is_active' => true, 'sort_order' => 0]);

    expect($saudi->fresh()->is_preorder_only)->toBeFalse();
});

it('loads the Qasr Al Mandi pre-order menu and keeps it out of ordinary ordering', function (): void {
    $this->seed(ArmansKitchenQasrAlMandiSeeder::class);
    $this->seed(ArmansKitchenQasrAlMandiSeeder::class);

    $category = MenuCategory::query()->withoutGlobalScopes()->where('name', 'Qasr Al Mandi')->firstOrFail();
    $kabsa = MenuItem::query()->withoutGlobalScopes()->where('name', 'Kabsa')->firstOrFail();

    expect($category->is_preorder_only)->toBeTrue()
        ->and(MenuCategory::query()->withoutGlobalScopes()->where('name', 'Qasr Al Mandi')->count())->toBe(1)
        ->and($category->items()->count())->toBe(7)
        ->and($kabsa->variants()->orderBy('sort_order')->pluck('price', 'name')->map(fn ($price) => (int) $price)->all())->toBe(['1:2' => 900, '1:4' => 1700]);

    // The menu book shows it; the POS does not.
    $this->get(route('web.preorder', ['branchCode' => 'br01']))->assertInertia(fn ($page) => $page
        ->where('categories', fn ($rows) => collect($rows)->contains(fn ($row) => $row['name'] === 'Qasr Al Mandi')));

    $this->actingAs($this->manager, 'staff')->get(route('admin.pos.index'))->assertInertia(fn ($page) => $page
        ->where('categories', fn ($rows) => ! collect($rows)->contains(fn ($row) => $row['name'] === 'Qasr Al Mandi')));

    // An ordinary order cannot hold a pre-order-only dish.
    $this->actingAs($this->manager, 'staff')
        ->post(route('admin.orders.store'), ['order_type' => 'takeaway', 'items' => [['menu_item_id' => $kabsa->id, 'variant_id' => $kabsa->variants()->first()->id, 'quantity' => 1]]])
        ->assertSessionHasErrors('items');

    // A pre-order takes it.
    $this->post(route('web.preorder.store', ['branchCode' => 'br01']), ['items' => [['menu_item_id' => $kabsa->id, 'variant_id' => $kabsa->variants()->first()->id, 'quantity' => 1]]])
        ->assertSessionHasNoErrors();

    expect(Preorder::query()->firstOrFail()->total)->toBe('900.00');
});

it('keeps a single pre-order only dish out of ordinary ordering while its category stays open', function (): void {
    $only = MenuItem::query()->create([
        'branch_id' => $this->branch->id,
        'menu_category_id' => $this->category->id,
        'name' => 'Special Mandi',
        'base_price' => 800,
        'is_available' => true,
        'is_preorder_only' => true,
    ]);

    $this->get(route('web.preorder', ['branchCode' => 'br01']))->assertInertia(fn ($page) => $page
        ->where('categories.0.items', fn ($items) => collect($items)->pluck('name')->contains('Special Mandi')));

    $this->actingAs($this->manager, 'staff')->get(route('admin.pos.index'))->assertInertia(fn ($page) => $page
        ->where('categories', fn ($rows) => ! collect($rows)->flatMap(fn ($row) => $row['items'])->pluck('name')->contains('Special Mandi')
            && collect($rows)->flatMap(fn ($row) => $row['items'])->pluck('id')->contains($this->item->id)));

    $this->actingAs($this->manager, 'staff')
        ->post(route('admin.orders.store'), ['order_type' => 'takeaway', 'items' => [['menu_item_id' => $only->id, 'quantity' => 1]]])
        ->assertSessionHasErrors('items');

    $this->actingAs($this->manager, 'staff')
        ->post(route('admin.orders.store'), ['order_type' => 'takeaway', 'items' => [['menu_item_id' => $this->item->id, 'quantity' => 1]]])
        ->assertSessionHasNoErrors();
});
