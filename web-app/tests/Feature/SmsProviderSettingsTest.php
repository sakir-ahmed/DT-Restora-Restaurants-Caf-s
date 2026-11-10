<?php

declare(strict_types=1);

use App\Models\Setting;
use App\Models\User;
use App\Services\Sms\SmsProviders;
use App\Services\SmsService;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
    config(['services.sms.demo' => false, 'services.bdbulksms.token' => null]);
    $this->owner = User::factory()->role('owner')->create();
});

it('saves the provider token encrypted and never sends it back to the page', function (): void {
    $this->actingAs($this->owner, 'staff')
        ->put(route('admin.settings.sms'), ['provider' => 'bdbulksms', 'fields' => ['token' => 'secret-token-123']])
        ->assertSessionHasNoErrors();

    $setting = Setting::current();

    expect($setting->sms_provider)->toBe('bdbulksms')
        ->and($setting->sms_config['bdbulksms']['token'])->toBe('secret-token-123')
        ->and(Setting::query()->toBase()->value('sms_config'))->not->toContain('secret-token-123');

    $this->actingAs($this->owner, 'staff')->get(route('admin.settings.index'))
        ->assertInertia(fn ($page) => $page
            ->where('sms.provider', 'bdbulksms')
            ->where('sms.providers.0.configured', true)
            ->where('sms.providers.0.fields.0.saved', true)
            ->where('sms.providers.0.fields.0.value', ''));

    // A blank secret keeps the saved one; a missing required value is rejected only when nothing is saved.
    $this->actingAs($this->owner, 'staff')
        ->put(route('admin.settings.sms'), ['provider' => 'bdbulksms', 'fields' => ['token' => '']])
        ->assertSessionHasNoErrors();

    expect(SmsProviders::credentials('bdbulksms')['token'])->toBe('secret-token-123');
});

it('requires a token the first time and rejects unknown providers', function (): void {
    $this->actingAs($this->owner, 'staff')
        ->put(route('admin.settings.sms'), ['provider' => 'bdbulksms', 'fields' => ['token' => '']])
        ->assertSessionHasErrors('fields.token');

    $this->actingAs($this->owner, 'staff')
        ->put(route('admin.settings.sms'), ['provider' => 'nope'])
        ->assertSessionHasErrors('provider');
});

it('sends through BD Bulk SMS and reads the JSON answer', function (): void {
    Setting::current()->update(['sms_provider' => 'bdbulksms', 'sms_config' => ['bdbulksms' => ['token' => 'tok']]]);

    Http::fake(['api.bdbulksms.net/*' => Http::sequence()
        ->push([['to' => '+8801700000000', 'message' => 'hi', 'status' => 'SENT', 'statusmsg' => 'SMS Sent Successfully To  +8801700000000']])
        ->push([['to' => '+8801', 'status' => 'FAILED', 'statusmsg' => '+8801 Invalid Number']])]);

    expect(app(SmsService::class)->send('01700000000', 'hi'))->toBeTrue();

    Http::assertSent(fn ($request) => $request->url() === 'https://api.bdbulksms.net/api.php?json'
        && $request['to'] === '01700000000'
        && $request['token'] === 'tok'
        && $request['message'] === 'hi');

    $result = app(SmsService::class)->deliver('01', 'hi');

    expect($result->sent)->toBeFalse()->and($result->message)->toBe('+8801 Invalid Number');
});

it('also understands the plain text answer', function (): void {
    Setting::current()->update(['sms_provider' => 'bdbulksms', 'sms_config' => ['bdbulksms' => ['token' => 'tok']]]);
    Http::fake(['api.bdbulksms.net/*' => Http::response('Ok: SMS Sent Successfully To +8801700000000')]);

    expect(app(SmsService::class)->send('01700000000', 'hi'))->toBeTrue();
});

it('does not call the gateway when no token is saved', function (): void {
    Setting::current()->update(['sms_provider' => 'bdbulksms', 'sms_config' => null]);
    Http::fake();

    expect(app(SmsService::class)->send('01700000000', 'hi'))->toBeFalse();
    Http::assertNothingSent();
});

it('sends a test SMS from Settings and reports the gateway answer', function (): void {
    Setting::current()->update(['sms_provider' => 'bdbulksms', 'sms_config' => ['bdbulksms' => ['token' => 'tok']]]);
    Http::fake(['api.bdbulksms.net/*' => Http::sequence()
        ->push([['status' => 'FAILED', 'statusmsg' => 'Invalid token']])
        ->push([['status' => 'SENT', 'statusmsg' => 'ok']])]);

    $this->actingAs($this->owner, 'staff')
        ->post(route('admin.settings.sms.test'), ['mobile' => '01700000000'])
        ->assertSessionHas('error');

    $this->actingAs($this->owner, 'staff')
        ->post(route('admin.settings.sms.test'), ['mobile' => '01700000000'])
        ->assertSessionHas('success');
});
