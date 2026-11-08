<?php

declare(strict_types=1);

use App\Models\Branch;
use App\Models\Customer;
use App\Models\DeliveryZone;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Services\PublicCache;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    $this->seed([ChartOfAccountsSeeder::class, RoleSeeder::class]);

    $this->branch = Branch::factory()->create(['code' => 'BR01', 'online_ordering_enabled' => true]);
    $this->item = MenuItem::factory()->create([
        'branch_id' => $this->branch->id,
        'menu_category_id' => MenuCategory::factory()->create(['branch_id' => $this->branch->id])->id,
        'base_price' => '250.00',
    ]);
    $this->zone = DeliveryZone::query()->create([
        'branch_id' => $this->branch->id, 'name' => 'Gulshan', 'fee' => '60.00', 'min_order' => '200.00', 'eta_minutes' => 30,
    ]);
    $this->customer = Customer::query()->create(['name' => 'Ayesha', 'mobile' => '8801711000099', 'mobile_verified_at' => now()]);

    session(['web_branch_id' => $this->branch->id]);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function settingsPayload(array $overrides = []): array
{
    return [
        'discount_limit_percent' => '10',
        'wastage_spike_multiplier' => '1.5',
        'pos_pin_minutes' => 5,
        'delivery_otp_enabled' => true,
        'website_enabled' => true,
        'online_delivery_enabled' => true,
        ...$overrides,
    ];
}

it('closes the public website when it is switched off, but not for staff or order tracking', function (): void {
    Setting::current()->update(['website_enabled' => false]);

    $this->get('/')->assertStatus(503);
    $this->get('/menu/br01')->assertStatus(503);
    $this->get('/robots.txt')->assertOk();
    $this->get('/manifest.webmanifest')->assertOk();

    // Order tracking is not closed: an unknown token is a plain 404, not the maintenance page.
    $this->get('/track/'.str_repeat('a', 40))->assertNotFound();

    $this->actingAs(User::factory()->role('owner')->create(), 'staff')->get('/')->assertOk();
});

it('lets the owner switch the website and delivery on or off from Settings', function (): void {
    $owner = User::factory()->role('owner')->create();

    $this->actingAs($owner, 'staff')
        ->put(route('admin.settings.update'), settingsPayload(['website_enabled' => false, 'online_delivery_enabled' => false, 'established_year' => 2013]))
        ->assertSessionHasNoErrors();

    $settings = Setting::current();

    expect($settings->website_enabled)->toBeFalse()
        ->and($settings->online_delivery_enabled)->toBeFalse()
        ->and($settings->established_year)->toBe(2013);

    $this->actingAs($owner, 'staff')
        ->put(route('admin.settings.update'), settingsPayload(['established_year' => 1500]))
        ->assertSessionHasErrors('established_year');
});

it('offers only pickup on the website when delivery is switched off', function (): void {
    Setting::current()->update(['online_delivery_enabled' => false]);

    $this->actingAs($this->customer, 'customer')->get('/checkout')
        ->assertInertia(fn ($page) => $page->where('delivery.enabled', false));

    $this->actingAs($this->customer, 'customer')->post('/checkout', [
        'order_type' => 'delivery',
        'delivery_zone_id' => $this->zone->id,
        'delivery_address' => 'House 5, Gulshan',
        'payment_method' => 'cod',
        'items' => [['menu_item_id' => $this->item->id, 'quantity' => 2]],
    ])->assertSessionHasErrors(['order_type' => __('web.err_delivery_off')]);

    expect(Order::query()->count())->toBe(0);

    Event::fake();
    Queue::fake();

    $this->actingAs($this->customer, 'customer')->post('/checkout', [
        'order_type' => 'pickup',
        'payment_method' => 'cod',
        'items' => [['menu_item_id' => $this->item->id, 'quantity' => 1]],
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect(Order::query()->count())->toBe(1)
        ->and(Order::query()->first()->order_type->value)->toBe('takeaway');
});

it('only delivers inside the listed areas, with the exact location typed after choosing one', function (): void {
    Event::fake();
    Queue::fake();

    $this->actingAs($this->customer, 'customer')->post('/checkout', [
        'order_type' => 'delivery',
        'delivery_address' => 'House 5, Road 10',
        'payment_method' => 'cod',
        'items' => [['menu_item_id' => $this->item->id, 'quantity' => 2]],
    ])->assertSessionHasErrors(['delivery_zone_id' => __('web.err_zone')]);

    // An area of another branch, or an inactive one, cannot be chosen.
    $inactive = DeliveryZone::query()->create(['branch_id' => $this->branch->id, 'name' => 'Far away', 'fee' => '10', 'min_order' => '0', 'is_active' => false]);

    $this->actingAs($this->customer, 'customer')->post('/checkout', [
        'order_type' => 'delivery',
        'delivery_zone_id' => $inactive->id,
        'delivery_address' => 'House 5, Road 10',
        'payment_method' => 'cod',
        'items' => [['menu_item_id' => $this->item->id, 'quantity' => 2]],
    ])->assertNotFound();

    $this->actingAs($this->customer, 'customer')->post('/checkout', [
        'order_type' => 'delivery',
        'delivery_zone_id' => $this->zone->id,
        'delivery_address' => 'House 5, Road 10',
        'payment_method' => 'cod',
        'items' => [['menu_item_id' => $this->item->id, 'quantity' => 2]],
    ])->assertSessionHasNoErrors();

    $order = Order::query()->where('status', '!=', 'void')->firstOrFail();

    expect((float) $order->delivery_fee)->toBe(60.0)
        ->and($order->delivery_zone_id)->toBe($this->zone->id)
        ->and($order->delivery_address)->toBe('House 5, Road 10, Gulshan');
});

it('marks a flexible area so the restaurant confirms the fee by phone', function (): void {
    Event::fake();
    Queue::fake();

    $flexible = DeliveryZone::query()->create(['branch_id' => $this->branch->id, 'name' => 'Other nearby area', 'fee' => '100', 'fee_type' => 'flexible', 'min_order' => '0', 'is_active' => true]);

    $this->actingAs($this->customer, 'customer')->get('/checkout')
        ->assertInertia(fn ($page) => $page
            ->has('zones', 2)
            ->where('zones.1.fee_type', 'flexible'));

    $this->actingAs($this->customer, 'customer')->post('/checkout', [
        'order_type' => 'delivery',
        'delivery_zone_id' => $flexible->id,
        'delivery_address' => 'Behind the school, near the mosque',
        'payment_method' => 'cod',
        'items' => [['menu_item_id' => $this->item->id, 'quantity' => 1]],
    ])->assertSessionHasNoErrors();

    $order = Order::query()->firstOrFail();

    expect($order->delivery_address)->toContain('Behind the school, near the mosque, Other nearby area')
        ->and($order->delivery_address)->toContain(__('web.fee_confirm_note'))
        ->and((float) $order->delivery_fee)->toBe(100.0);
});

it('keeps website delivery off until at least one area is active', function (): void {
    DeliveryZone::query()->update(['is_active' => false]);

    $this->actingAs($this->customer, 'customer')->get('/checkout')
        ->assertInertia(fn ($page) => $page->where('delivery.enabled', false)->has('zones', 0));

    $this->actingAs($this->customer, 'customer')->post('/checkout', [
        'order_type' => 'delivery',
        'delivery_zone_id' => $this->zone->id,
        'delivery_address' => 'House 5',
        'payment_method' => 'cod',
        'items' => [['menu_item_id' => $this->item->id, 'quantity' => 2]],
    ])->assertSessionHasErrors('order_type');
});

it('lets the owner manage the delivery areas of the branch from Settings', function (): void {
    $owner = User::factory()->role('owner', $this->branch->id)->create();

    $this->actingAs($owner, 'staff')->post(route('admin.settings.delivery-zones.store'), [
        'name' => 'Mohammadpur', 'name_bn' => 'মোহাম্মদপুর', 'fee' => '50', 'fee_type' => 'fixed', 'min_order' => '200', 'eta_minutes' => 40, 'is_active' => true,
    ])->assertSessionHasNoErrors();

    $zone = DeliveryZone::query()->where('name', 'Mohammadpur')->firstOrFail();

    expect($zone->branch_id)->toBe($this->branch->id)->and($zone->name_bn)->toBe('মোহাম্মদপুর');

    $this->actingAs($owner, 'staff')->put(route('admin.settings.delivery-zones.update', $zone), [
        'name' => 'Mohammadpur', 'fee' => '70', 'fee_type' => 'flexible', 'min_order' => '200', 'is_active' => false,
    ])->assertSessionHasNoErrors();

    expect((float) $zone->fresh()->fee)->toBe(70.0)->and($zone->fresh()->fee_type)->toBe('flexible')->and($zone->fresh()->is_active)->toBeFalse();

    $this->actingAs($owner, 'staff')->post(route('admin.settings.delivery-zones.store'), [
        'name' => '', 'fee' => '-5', 'fee_type' => 'anywhere', 'min_order' => '0', 'is_active' => true,
    ])->assertSessionHasErrors(['name', 'fee', 'fee_type']);

    $this->actingAs($owner, 'staff')->get(route('admin.settings.index'))
        ->assertInertia(fn ($page) => $page->has('deliveryZones', 2));

    $this->actingAs($owner, 'staff')->delete(route('admin.settings.delivery-zones.destroy', $zone))->assertSessionHasNoErrors();

    expect(DeliveryZone::query()->whereKey($zone->id)->exists())->toBeFalse();
});

it('does not let the owner change an area of another branch', function (): void {
    $other = Branch::factory()->create(['code' => 'BR02']);
    $foreign = DeliveryZone::query()->create(['branch_id' => $other->id, 'name' => 'Elsewhere', 'fee' => '10', 'min_order' => '0']);
    $owner = User::factory()->role('owner', $this->branch->id)->create();

    $this->actingAs($owner, 'staff')->delete(route('admin.settings.delivery-zones.destroy', $foreign))->assertNotFound();
});

it('shows years of service only when the opening year is set', function (): void {
    $this->get('/')->assertInertia(fn ($page) => $page->where('stats.years', null));

    Setting::current()->update(['established_year' => now()->year - 5]);
    PublicCache::bump();

    $this->get('/')->assertInertia(fn ($page) => $page->where('stats.years', 5));
});
