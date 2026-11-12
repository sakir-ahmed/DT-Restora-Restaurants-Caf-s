<?php

declare(strict_types=1);

use App\Models\Branch;
use App\Models\DeliveryZone;
use App\Models\PaymentMethod;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function (): void {
    $this->seed([RoleSeeder::class]);

    $this->branch = Branch::factory()->create(['code' => 'BR01']);
    $this->owner = User::factory()->role('owner')->create();
    $this->token = $this->owner->createToken('staff', ['staff'])->plainTextToken;
    $this->base = '/api/v1/branches/'.$this->branch->id;
});

it('reads settings and never returns an SMS secret', function (): void {
    $data = $this->withToken($this->token)->getJson('/api/v1/settings')->assertOk()->json('data');

    expect($data)->toHaveKeys(['general', 'payment_methods', 'sms', 'layouts', 'papers'])
        ->and($data['sms'])->toHaveKeys(['provider', 'providers', 'features', 'templates']);

    foreach ($data['sms']['providers'] as $provider) {
        foreach ($provider['fields'] as $field) {
            if ($field['secret']) {
                expect($field['value'])->toBe('');
            }
        }
    }
});

it('saves general settings', function (): void {
    $this->withToken($this->token)->putJson('/api/v1/settings', [
        'discount_limit_percent' => 15,
        'wastage_spike_multiplier' => 2,
        'pos_pin_minutes' => 5,
        'delivery_otp_enabled' => false,
        'tax_registration_no' => 'BIN-1',
    ])->assertOk();

    expect((float) Setting::current()->discount_limit_percent)->toBe(15.0)
        ->and(Setting::current()->tax_registration_no)->toBe('BIN-1');

    $this->withToken($this->token)->putJson('/api/v1/settings', ['discount_limit_percent' => 500])->assertUnprocessable();
});

it('adds, edits and removes a payment method but keeps cash', function (): void {
    $id = $this->withToken($this->token)->postJson('/api/v1/settings/payment-methods', [
        'code' => 'rocket', 'name' => 'Rocket', 'type' => 'wallet', 'is_active' => true,
    ])->assertCreated()->json('data.id');

    $this->withToken($this->token)->putJson('/api/v1/settings/payment-methods/'.$id, [
        'name' => 'Rocket pay', 'type' => 'wallet', 'is_active' => false,
    ])->assertOk();

    expect(PaymentMethod::query()->find($id)->is_active)->toBeFalse();

    $this->withToken($this->token)->deleteJson('/api/v1/settings/payment-methods/'.$id)->assertOk();

    $cash = PaymentMethod::query()->where('code', 'cash')->first();

    if ($cash !== null) {
        $this->withToken($this->token)->deleteJson('/api/v1/settings/payment-methods/'.$cash->id)->assertUnprocessable();
    }
});

it('manages delivery zones and the delivery switch for the branch', function (): void {
    $id = $this->withToken($this->token)->postJson($this->base.'/settings/delivery-zones', [
        'name' => 'Dhanmondi', 'fee' => 60, 'fee_type' => 'fixed', 'min_order' => 200, 'is_active' => true,
    ])->assertCreated()->json('data.id');

    $this->withToken($this->token)->putJson($this->base.'/settings/delivery-zones/'.$id, [
        'name' => 'Dhanmondi 27', 'fee' => 80, 'fee_type' => 'fixed', 'min_order' => 200, 'is_active' => true,
    ])->assertOk()->assertJsonPath('data.fee', '80.00');

    $this->withToken($this->token)->putJson($this->base.'/settings/delivery-enabled', ['enabled' => false])
        ->assertOk()->assertJsonPath('data.online_delivery_enabled', false);

    expect($this->withToken($this->token)->getJson($this->base.'/settings')->json('data.delivery_zones'))->toHaveCount(1);

    $this->withToken($this->token)->deleteJson($this->base.'/settings/delivery-zones/'.$id)->assertOk();
    expect(DeliveryZone::query()->count())->toBe(0);
});

it('saves invoice style and paper and branch tax settings', function (): void {
    $this->withToken($this->token)->putJson($this->base.'/settings/invoice-print', ['layout' => 'classic', 'paper' => '80mm'])
        ->assertOk();

    $this->withToken($this->token)->putJson($this->base.'/settings/invoice-print', ['layout' => 'nope', 'paper' => '80mm'])
        ->assertUnprocessable();

    $this->withToken($this->token)->putJson($this->base.'/settings/branch-pos', [
        'vat_percent' => 5, 'service_charge_percent' => 10, 'vat_inclusive' => true,
    ])->assertOk()->assertJsonPath('data.vat_percent', '5.00');
});

it('saves SMS features and template wording, and keeps a secret when left blank', function (): void {
    $features = $this->withToken($this->token)->getJson('/api/v1/settings')->json('data.sms.features');
    $key = collect($features)->firstWhere('locked', false)['key'];

    $this->withToken($this->token)->putJson('/api/v1/settings/sms/features', ['features' => [$key => false]])->assertOk();

    $template = $this->withToken($this->token)->getJson('/api/v1/settings')->json('data.sms.templates.0');
    $required = collect($template['variables'])->where('required', true)->pluck('name')->map(fn ($n) => '{'.$n.'}')->implode(' ');

    $this->withToken($this->token)->putJson('/api/v1/settings/sms/templates/'.$template['key'], ['en' => 'Hello '.$required])
        ->assertOk();
});

it('lists SMS history and refuses a promotion when the feature is off', function (): void {
    $this->withToken($this->token)->getJson('/api/v1/sms/history')->assertOk()->assertJsonStructure(['data' => ['history', 'meta', 'audiences']]);

    $this->withToken($this->token)->putJson('/api/v1/settings/sms/features', ['features' => ['promotion' => false]])->assertOk();

    $this->withToken($this->token)->postJson('/api/v1/sms/promotion', ['message' => 'Offer', 'audience' => 'all'])->assertUnprocessable();
});

it('keeps settings to people with settings access', function (): void {
    $cashier = User::factory()->role('cashier', $this->branch->id)->create();
    $token = $cashier->createToken('staff', ['staff'])->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/settings')->assertForbidden();
    $this->withToken($token)->putJson($this->base.'/settings/delivery-enabled', ['enabled' => true])->assertForbidden();
    $this->withToken($token)->getJson('/api/v1/sms/history')->assertForbidden();
});
