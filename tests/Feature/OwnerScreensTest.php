<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->role('owner')->create();
    $this->cashier = User::factory()->role('cashier')->create();
});

it('lets the owner open settings, the activity log and the customer directory', function (): void {
    $this->actingAs($this->owner, 'staff')->get('/admin/settings')->assertOk();
    $this->actingAs($this->owner, 'staff')->get('/admin/activity-log')->assertOk();
    $this->actingAs($this->owner, 'staff')->get('/admin/customers')->assertOk();
});

it('hides settings and the activity log from a cashier', function (): void {
    $this->actingAs($this->cashier, 'staff')->get('/admin/settings')->assertForbidden();
    $this->actingAs($this->cashier, 'staff')->get('/admin/activity-log')->assertForbidden();
    $this->actingAs($this->cashier, 'staff')->get('/admin/customers')->assertOk();
});

it('saves operational settings and records the change', function (): void {
    $this->actingAs($this->owner, 'staff')->put('/admin/settings', [
        'discount_limit_percent' => 15,
        'wastage_spike_multiplier' => 2,
        'pos_pin_minutes' => 8,
        'delivery_otp_enabled' => false,
    ])->assertRedirect();

    $settings = Setting::current();

    expect((float) $settings->discount_limit_percent)->toBe(15.0)
        ->and((float) $settings->wastage_spike_multiplier)->toBe(2.0)
        ->and($settings->pos_pin_minutes)->toBe(8)
        ->and($settings->delivery_otp_enabled)->toBeFalse();

    $this->actingAs($this->owner, 'staff')->get('/admin/activity-log')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('entries'));
});

it('lists customers matching a mobile search', function (): void {
    Customer::query()->create(['name' => 'Mina', 'mobile' => '8801711111111']);
    Customer::query()->create(['name' => 'Rafi', 'mobile' => '8801811111111']);

    $response = $this->actingAs($this->owner, 'staff')->get('/admin/customers?search=1711111111');

    $response->assertOk();

    $names = collect($response->viewData('page')['props']['customers'])->pluck('name');

    expect($names->all())->toBe(['Mina']);
});
