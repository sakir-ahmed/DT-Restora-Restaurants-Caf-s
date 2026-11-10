<?php

declare(strict_types=1);

use App\Models\Branch;
use App\Models\Customer;
use App\Models\DeliveryZone;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Services\OtpService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    $this->seed([ChartOfAccountsSeeder::class, RoleSeeder::class]);

    $this->branch = Branch::factory()->create(['code' => 'BR01', 'online_ordering_enabled' => true]);
    $category = MenuCategory::factory()->create(['branch_id' => $this->branch->id]);
    $this->item = MenuItem::factory()->create([
        'branch_id' => $this->branch->id,
        'menu_category_id' => $category->id,
        'base_price' => '250.00',
    ]);
    $this->zone = DeliveryZone::query()->create([
        'branch_id' => $this->branch->id,
        'name' => 'Gulshan',
        'fee' => '60.00',
        'min_order' => '200.00',
        'eta_minutes' => 30,
    ]);

    session(['web_branch_id' => $this->branch->id]);
});

it('tells a customer exactly what is missing from a delivery order', function (): void {
    $customer = Customer::query()->create(['name' => 'Ayesha', 'mobile' => '8801711000099']);

    $this->actingAs($customer, 'customer')->post('/checkout', [
        'order_type' => 'delivery',
        'payment_method' => 'cod',
        'items' => [['menu_item_id' => $this->item->id, 'quantity' => 1]],
    ])->assertSessionHasErrors([
        'delivery_zone_id' => __('web.err_zone'),
        'delivery_address' => __('web.err_address'),
    ]);

    expect(Order::query()->count())->toBe(0);
});

it('rejects an empty cart with a friendly message', function (): void {
    $customer = Customer::query()->create(['name' => 'Ayesha', 'mobile' => '8801711000099']);

    $this->actingAs($customer, 'customer')->post('/checkout', [
        'order_type' => 'pickup',
        'payment_method' => 'cod',
        'items' => [],
    ])->assertSessionHasErrors(['items' => __('web.cart_empty')]);
});

it('asks a guest to verify their mobile before ordering', function (): void {
    $this->post('/checkout', [
        'order_type' => 'pickup',
        'payment_method' => 'cod',
        'customer_mobile' => '01711223344',
        'items' => [['menu_item_id' => $this->item->id, 'quantity' => 1]],
    ])->assertSessionHasErrors(['guest_otp' => __('web.otp_required')]);
});

it('hands the order summary to the tracking page for the success dialog', function (): void {
    Event::fake();
    Queue::fake();

    $customer = Customer::query()->create(['name' => 'Ayesha', 'mobile' => '8801711000099']);

    $response = $this->actingAs($customer, 'customer')->post('/checkout', [
        'order_type' => 'delivery',
        'delivery_zone_id' => $this->zone->id,
        'delivery_address' => 'House 5, Road 10, Gulshan',
        'payment_method' => 'cod',
        'items' => [['menu_item_id' => $this->item->id, 'quantity' => 1]],
    ]);

    $order = Order::query()->firstOrFail();

    $response->assertRedirect(route('web.track', ['token' => $order->tracking_token]));

    $this->actingAs($customer, 'customer')->get($response->headers->get('Location'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('web/Track')
            ->where('flash.success', __('web.order_placed', ['invoice' => $order->invoice_no]))
            ->where('flash.placed.invoice_no', $order->invoice_no)
            ->where('flash.placed.payment_method', 'cod')
            ->where('flash.placed.eta_minutes', 30)
            ->where('flash.placed.branch', $this->branch->name));

    // The summary is a one-time flash: a reload does not reopen the dialog.
    $this->actingAs($customer, 'customer')->get($response->headers->get('Location'))
        ->assertInertia(fn ($page) => $page->where('flash.placed', null));
});

it('lets a local install without an SMS gateway read the code from the log', function (): void {
    app()['env'] = 'local';
    config(['services.bdbulksms.token' => null]);
    Log::spy();

    expect(app(OtpService::class)->send('01711223344', '127.0.0.1'))->toBeTrue();

    Log::shouldHaveReceived('info')->withArgs(fn (string $message): bool => str_contains($message, 'OTP for local testing'));
});

it('does not pretend a code was sent outside local installs', function (): void {
    config(['services.bdbulksms.token' => null]);

    expect(app(OtpService::class)->send('01711223344', '127.0.0.1'))->toBeFalse();
});

it('reports a delivery failure instead of blaming rate limits', function (): void {
    config(['services.bdbulksms.token' => null]);

    $this->post('/customer/otp/send', ['mobile' => '01711223344'])
        ->assertSessionHasErrors(['mobile' => __('api.otp_send_failed')]);
});

it('keeps the success and error messages in both languages', function (): void {
    foreach (['err_zone', 'err_address', 'err_mobile', 'err_otp_length', 'order_success_title', 'checkout_fix'] as $key) {
        expect(__('web.'.$key, [], 'bn'))->not->toBe('web.'.$key)
            ->and(__('web.'.$key, [], 'bn'))->not->toBe(__('web.'.$key, [], 'en'));
    }
});

it('ships every translation the checkout page prints', function (): void {
    $translations = $this->get('/checkout')->viewData('page')['props']['translations'];

    foreach (['receipt.subtotal', 'receipt.vat', 'receipt.grand_total', 'receipt.discount', 'receipt.service_charge', 'orders.type.delivery', 'payment_method.bkash', 'coupons.code', 'staff.mobile', 'common.apply'] as $key) {
        expect($translations)->toHaveKey($key);
    }
});
