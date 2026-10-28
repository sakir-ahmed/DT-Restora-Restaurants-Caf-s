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
        'delivery_mode' => 'zones',
        'delivery_flat_fee' => '0',
        'delivery_min_order' => '0',
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

it('lets the owner switch the website and delivery options from Settings', function (): void {
    $owner = User::factory()->role('owner')->create();

    $this->actingAs($owner, 'staff')
        ->put(route('admin.settings.update'), settingsPayload([
            'website_enabled' => false,
            'online_delivery_enabled' => true,
            'delivery_mode' => 'address_only',
            'delivery_flat_fee' => '80',
            'delivery_min_order' => '300',
            'delivery_eta_minutes' => 45,
            'established_year' => 2013,
        ]))
        ->assertSessionHasNoErrors();

    $settings = Setting::current();

    expect($settings->website_enabled)->toBeFalse()
        ->and($settings->delivery_mode)->toBe('address_only')
        ->and((float) $settings->delivery_flat_fee)->toBe(80.0)
        ->and($settings->delivery_eta_minutes)->toBe(45)
        ->and($settings->established_year)->toBe(2013);

    $this->actingAs($owner, 'staff')
        ->put(route('admin.settings.update'), settingsPayload(['delivery_mode' => 'nonsense', 'established_year' => 1500, 'delivery_flat_fee' => '-1']))
        ->assertSessionHasErrors(['delivery_mode', 'established_year', 'delivery_flat_fee']);
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

it('takes a delivery address without an area when the mode is address only', function (): void {
    Setting::current()->update(['delivery_mode' => 'address_only', 'delivery_flat_fee' => '80', 'delivery_min_order' => '300', 'delivery_eta_minutes' => 45]);

    Event::fake();
    Queue::fake();

    $this->actingAs($this->customer, 'customer')->post('/checkout', [
        'order_type' => 'delivery',
        'delivery_address' => 'House 5, Road 10, Mohammadpur',
        'payment_method' => 'cod',
        'items' => [['menu_item_id' => $this->item->id, 'quantity' => 1]],
    ])->assertSessionHasErrors('delivery_address');

    // The order that missed the minimum is voided, never kept.
    expect(Order::query()->where('status', '!=', 'void')->count())->toBe(0);

    $this->actingAs($this->customer, 'customer')->post('/checkout', [
        'order_type' => 'delivery',
        'delivery_address' => 'House 5, Road 10, Mohammadpur',
        'payment_method' => 'cod',
        'items' => [['menu_item_id' => $this->item->id, 'quantity' => 2]],
    ])->assertSessionHasNoErrors();

    $order = Order::query()->where('status', '!=', 'void')->firstOrFail();

    expect((float) $order->delivery_fee)->toBe(80.0)
        ->and($order->delivery_zone_id)->toBeNull()
        ->and($order->delivery_address)->toBe('House 5, Road 10, Mohammadpur');
});

it('still requires an area when the mode is delivery areas', function (): void {
    $this->actingAs($this->customer, 'customer')->post('/checkout', [
        'order_type' => 'delivery',
        'delivery_address' => 'House 5, Gulshan',
        'payment_method' => 'cod',
        'items' => [['menu_item_id' => $this->item->id, 'quantity' => 2]],
    ])->assertSessionHasErrors(['delivery_zone_id' => __('web.err_zone')]);
});

it('shows years of service only when the opening year is set', function (): void {
    $this->get('/')->assertInertia(fn ($page) => $page->where('stats.years', null));

    Setting::current()->update(['established_year' => now()->year - 5]);
    PublicCache::bump();

    $this->get('/')->assertInertia(fn ($page) => $page->where('stats.years', 5));
});
