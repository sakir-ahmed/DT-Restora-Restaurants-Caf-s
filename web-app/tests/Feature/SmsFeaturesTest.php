<?php

declare(strict_types=1);

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Jobs\SendSmsJob;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Setting;
use App\Models\SmsLog;
use App\Models\User;
use App\Services\DueReminderService;
use App\Services\Sms\SmsFeatures;
use App\Services\SmsPromotionService;
use App\Services\SmsService;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

function makeSmsOrder(array $attributes = []): Order
{
    static $n = 0;
    $n++;

    return Order::query()->create([
        'branch_id' => Branch::factory()->create(['code' => 'SM'.$n])->id,
        'invoice_no' => 'SM-INV-'.$n,
        'order_type' => 'takeaway',
        'channel' => OrderChannel::Pos->value,
        'status' => OrderStatus::Served->value,
        'subtotal' => '500.00',
        'total' => '500.00',
        ...$attributes,
    ]);
}

function makeSmsCustomer(?string $mobile, string $name = 'Test Customer'): Customer
{
    return Customer::query()->create(['name' => $name, 'mobile' => $mobile, 'is_active' => true]);
}

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
    config(['services.sms.demo' => false]);
    Setting::current()->update(['sms_provider' => 'bdbulksms', 'sms_config' => ['bdbulksms' => ['token' => 'tok']]]);
    Http::fake(['api.bdbulksms.net/*' => Http::response([['status' => 'SENT', 'statusmsg' => 'ok']])]);
    $this->owner = User::factory()->role('owner')->create();
});

it('records sent, failed and skipped messages in the history and hides OTP codes', function (): void {
    $sms = app(SmsService::class);

    expect($sms->send('01700000001', 'Hello', 'order_confirmed'))->toBeTrue();

    Setting::current()->update(['sms_features' => ['birthday' => false]]);

    expect($sms->send('01700000002', 'Happy birthday', 'birthday'))->toBeFalse();

    $sms->send('01700000003', 'Your code is 123456', 'otp');

    $logs = SmsLog::query()->orderBy('id')->get();

    expect($logs->pluck('status')->all())->toBe(['sent', 'skipped', 'sent'])
        ->and($logs[0]->provider)->toBe('bdbulksms')
        ->and($logs[2]->message)->not->toContain('123456');

    Http::assertSentCount(2);
});

it('keeps sign-in codes on even if someone tries to switch them off', function (): void {
    $this->actingAs($this->owner, 'staff')
        ->put(route('admin.settings.sms.features'), ['features' => ['otp' => false, 'invoice' => true, 'win_back' => false]])
        ->assertSessionHasNoErrors();

    expect(SmsFeatures::enabled('otp'))->toBeTrue()
        ->and(SmsFeatures::enabled('invoice'))->toBeTrue()
        ->and(SmsFeatures::enabled('win_back'))->toBeFalse()
        ->and(SmsFeatures::enabled('birthday'))->toBeTrue();
});

it('texts the invoice when a counter order is paid, only if the feature is on', function (): void {
    Bus::fake([SendSmsJob::class]);
    $customer = makeSmsCustomer('01711111111');
    $order = makeSmsOrder(['customer_id' => $customer->id, 'channel' => OrderChannel::Pos, 'status' => OrderStatus::Served->value, 'is_due' => false]);

    $order->update(['status' => OrderStatus::Paid->value]);
    Bus::assertNotDispatched(SendSmsJob::class);

    Setting::current()->update(['sms_features' => ['invoice' => true]]);
    $second = makeSmsOrder(['customer_id' => $customer->id, 'channel' => OrderChannel::Pos, 'status' => OrderStatus::Served->value, 'is_due' => false]);
    $second->update(['status' => OrderStatus::Paid->value]);

    Bus::assertDispatched(SendSmsJob::class, fn (SendSmsJob $job): bool => $job->feature === 'invoice' && str_contains($job->message, $second->invoice_no));
});

it('reminds a customer once a week about a due sale and respects the switch', function (): void {
    Bus::fake([SendSmsJob::class]);
    Cache::flush();
    $customer = makeSmsCustomer('01722222222');
    makeSmsOrder(['customer_id' => $customer->id, 'is_due' => true, 'total' => '500.00', 'paid_at' => now()->subDays(2), 'status' => OrderStatus::Paid->value]);

    expect(app(DueReminderService::class)->send())->toBe(0);

    Setting::current()->update(['sms_features' => ['due_reminder' => true]]);

    expect(app(DueReminderService::class)->send())->toBe(1)
        ->and(app(DueReminderService::class)->send())->toBe(0);

    Bus::assertDispatched(SendSmsJob::class, fn (SendSmsJob $job): bool => $job->feature === 'due_reminder' && str_contains($job->message, '500.00'));
});

it('sends a promotion to the chosen group once per number', function (): void {
    Bus::fake([SendSmsJob::class]);
    foreach ([1, 2] as $n) {
        makeSmsCustomer(null, 'No Mobile '.$n);
    }
    makeSmsCustomer('01733333333');
    makeSmsCustomer('+8801733333333');
    makeSmsCustomer('01744444444');

    expect(app(SmsPromotionService::class)->counts()['all'])->toBe(3);

    $this->actingAs($this->owner, 'staff')
        ->post(route('admin.sms.promotion'), ['message' => 'Eid offer 20% off', 'audience' => 'all'])
        ->assertSessionHas('success');

    Bus::assertDispatchedTimes(SendSmsJob::class, 2);

    Setting::current()->update(['sms_features' => ['promotion' => false]]);

    $this->actingAs($this->owner, 'staff')
        ->post(route('admin.sms.promotion'), ['message' => 'Again', 'audience' => 'all'])
        ->assertSessionHas('error');
});

it('keeps history and promotion in the SMS module, behind its own permission', function (): void {
    SmsLog::query()->create(['feature' => 'invoice', 'mobile' => '01755555555', 'message' => 'a', 'status' => 'sent']);

    $this->actingAs($this->owner, 'staff')->get(route('admin.sms.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/sms/Index')->has('audiences'));

    $cashier = User::factory()->role('cashier')->create();

    $this->actingAs($cashier, 'staff')->get(route('admin.sms.index'))->assertForbidden();
    $this->actingAs($cashier, 'staff')->post(route('admin.sms.promotion'), ['message' => 'x', 'audience' => 'all'])->assertForbidden();

    $this->actingAs($this->owner, 'staff')->get(route('admin.settings.index'))
        ->assertInertia(fn ($page) => $page->has('smsFeatures', 10)->has('smsTemplates', 10)->missing('smsHistory'));
});
