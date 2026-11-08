<?php

declare(strict_types=1);

use App\Jobs\SendSmsJob;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\DeliveryZone;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Services\OtpService;
use App\Services\SmsService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Http;
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

function demoSmsOnNextPage($test, string $url = '/'): array
{
    return $test->get($url)->viewData('page')['props']['demoSms'];
}

it('shows the verification code as a toast message instead of sending it', function (): void {
    config(['services.sms.demo' => true, 'services.bdbulksms.token' => 'real-token']);
    Http::fake();

    $this->post('/customer/otp/send', ['mobile' => '01711223344'])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', __('web.otp_sent'));

    Http::assertNothingSent();

    $messages = demoSmsOnNextPage($this);

    expect($messages)->toHaveCount(1)
        ->and($messages[0]['mobile'])->toBe('8801711223344')
        ->and($messages[0]['message'])->toMatch('/\d{6}/')
        ->and($messages[0])->toHaveKeys(['id', 'at']);
});

it('shows each captured message once', function (): void {
    config(['services.sms.demo' => true]);

    $this->post('/customer/otp/send', ['mobile' => '01711223344']);

    expect(demoSmsOnNextPage($this))->toHaveCount(1)
        ->and(demoSmsOnNextPage($this))->toBe([]);
});

it('shows the full order confirmation text after checkout', function (): void {
    config(['services.sms.demo' => true]);

    $customer = Customer::query()->create(['name' => 'Ayesha', 'mobile' => '8801711000099']);

    $response = $this->actingAs($customer, 'customer')->post('/checkout', [
        'order_type' => 'delivery',
        'delivery_zone_id' => $this->zone->id,
        'delivery_address' => 'House 5, Road 10, Gulshan',
        'payment_method' => 'cod',
        'items' => [['menu_item_id' => $this->item->id, 'quantity' => 1]],
    ]);

    $order = Order::query()->firstOrFail();

    // Demo mode runs the SMS in the same request, so the toast is ready on the next page.
    $messages = $this->actingAs($customer, 'customer')->get($response->headers->get('Location'))
        ->viewData('page')['props']['demoSms'];

    expect($messages)->toHaveCount(1)
        ->and($messages[0]['mobile'])->toBe('8801711000099')
        ->and($messages[0]['message'])->toBe(__('web.sms_order_confirmed', ['invoice' => $order->invoice_no, 'total' => $order->total]));
});

it('never shows or captures messages when demo mode is off', function (): void {
    config(['services.sms.demo' => false, 'services.bdbulksms.token' => null]);

    $this->post('/customer/otp/send', ['mobile' => '01711223344'])
        ->assertSessionHasErrors(['mobile' => __('api.otp_send_failed')]);

    expect(demoSmsOnNextPage($this))->toBe([]);
});

it('ignores demo mode in production so codes never reach the browser', function (): void {
    config(['services.sms.demo' => true, 'services.bdbulksms.token' => null]);
    app()['env'] = 'production';

    expect(SmsService::isDemo())->toBeFalse()
        ->and(app(SmsService::class)->send('8801711223344', 'Your code is 123456'))->toBeFalse()
        ->and(demoSmsOnNextPage($this))->toBe([]);
});

it('queues messages normally when demo mode is off', function (): void {
    config(['services.sms.demo' => false]);
    Queue::fake();

    SendSmsJob::deliver('8801711223344', 'Hello');

    Queue::assertPushed(SendSmsJob::class);
});

it('keeps messages from queues and the scheduler for the next page', function (): void {
    config(['services.sms.demo' => true]);

    SendSmsJob::deliver('8801711223344', 'Happy birthday');

    $messages = demoSmsOnNextPage($this);

    expect($messages)->toHaveCount(1)
        ->and($messages[0]['message'])->toBe('Happy birthday');
});

it('shows the code as a toast on a local install, not only in the log', function (): void {
    app()['env'] = 'local';
    config(['services.sms.demo' => true, 'services.bdbulksms.token' => null]);

    expect(app(OtpService::class)->send('01711223344', '127.0.0.1'))->toBeTrue();

    $messages = demoSmsOnNextPage($this);

    expect($messages)->toHaveCount(1)
        ->and($messages[0]['message'])->toMatch('/\d{6}/');
});

it('never locks a tester out of verification codes in demo mode', function (): void {
    config(['services.sms.demo' => true]);

    foreach (range(1, 8) as $attempt) {
        $this->post('/customer/otp/send', ['mobile' => '01711223344'])->assertSessionHasNoErrors();
    }

    expect(demoSmsOnNextPage($this))->toHaveCount(8);
});

it('still rate limits code requests when demo mode is off', function (): void {
    config(['services.sms.demo' => false, 'services.bdbulksms.token' => 'real-token']);
    Http::fake(['*' => Http::response('Ok')]);

    foreach (range(1, 5) as $attempt) {
        $this->post('/customer/otp/send', ['mobile' => '01711223344'])->assertSessionHasNoErrors();
    }

    $this->post('/customer/otp/send', ['mobile' => '01711223344'])
        ->assertSessionHasErrors(['mobile' => __('api.otp_rate_limited')]);
});

it('tells the page whether demo mode is on so a stale server is easy to spot', function (): void {
    config(['services.sms.demo' => true]);
    expect($this->get('/')->viewData('page')['props']['smsDemo'])->toBeTrue();

    config(['services.sms.demo' => false]);
    expect($this->get('/')->viewData('page')['props']['smsDemo'])->toBeFalse();

    config(['services.sms.demo' => true]);
    app()['env'] = 'production';
    expect($this->get('/')->viewData('page')['props']['smsDemo'])->toBeFalse();
});
