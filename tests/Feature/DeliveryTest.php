<?php

declare(strict_types=1);

use App\Actions\Delivery\AssignRiderAction;
use App\Actions\Delivery\UpdateDeliveryStatusAction;
use App\Actions\Sales\CreateOrderAction;
use App\Enums\OrderChannel;
use App\Enums\OrderType;
use App\Events\DeliveryAssigned;
use App\Events\RiderLocationUpdated;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Rider;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->seed([ChartOfAccountsSeeder::class, RoleSeeder::class]);

    $this->branch = Branch::factory()->create(['code' => 'BR01']);
    $this->manager = User::factory()->role('branch-manager', $this->branch->id)->create();

    $category = MenuCategory::factory()->create(['branch_id' => $this->branch->id]);
    $this->item = MenuItem::factory()->create([
        'branch_id' => $this->branch->id,
        'menu_category_id' => $category->id,
        'base_price' => '500.00',
    ]);

    $this->customer = Customer::query()->create(['name' => 'Ayesha', 'mobile' => '8801711000099']);

    $this->riderUser = User::factory()->role('rider', $this->branch->id)->create(['name' => 'Sohel Rider']);
    $this->rider = Rider::query()->create(['user_id' => $this->riderUser->id, 'vehicle' => 'motorcycle']);

    $this->otherRiderUser = User::factory()->role('rider', $this->branch->id)->create();
    $this->otherRider = Rider::query()->create(['user_id' => $this->otherRiderUser->id]);

    $this->order = app(CreateOrderAction::class)->execute(
        branch: $this->branch,
        type: OrderType::Delivery,
        lines: [['menu_item_id' => $this->item->id, 'quantity' => 1]],
        customer: $this->customer,
        channel: OrderChannel::Website,
        terminalCode: 'WEB',
        deliveryFee: 60,
        deliveryAddress: 'House 5, Gulshan',
    );
});

it('assigns a rider with COD amount and delivery OTP', function (): void {
    Event::fake();

    $delivery = app(AssignRiderAction::class)->execute($this->order, $this->rider, $this->manager);

    expect($delivery->status)->toBe(Delivery::STATUS_ASSIGNED)
        ->and((float) $delivery->cod_amount)->toBe((float) $this->order->total)
        ->and($delivery->otp_code)->toBeTruthy()
        ->and($delivery->assigned_at)->toBeTruthy();

    Event::assertDispatched(DeliveryAssigned::class);
});

it('prevents double assignment', function (): void {
    app(AssignRiderAction::class)->execute($this->order, $this->rider, $this->manager);

    expect(fn () => app(AssignRiderAction::class)->execute($this->order, $this->otherRider, $this->manager))
        ->toThrow(ValidationException::class);
});

it('walks the rider status flow requiring the OTP at handover', function (): void {
    Queue::fake();

    $delivery = app(AssignRiderAction::class)->execute($this->order, $this->rider, $this->manager);
    $action = app(UpdateDeliveryStatusAction::class);

    $action->execute($delivery, Delivery::STATUS_PICKED_UP, rider: $this->riderUser);
    $action->execute($delivery, Delivery::STATUS_ON_THE_WAY, rider: $this->riderUser);

    // Wrong OTP rejected.
    expect(fn () => $action->execute($delivery->fresh(), Delivery::STATUS_DELIVERED, '9999', rider: $this->riderUser))
        ->toThrow(ValidationException::class);

    // Another rider cannot touch it.
    expect(fn () => $action->execute($delivery->fresh(), Delivery::STATUS_DELIVERED, $delivery->otp_code, rider: $this->otherRiderUser))
        ->toThrow(ValidationException::class);

    $action->execute($delivery->fresh(), Delivery::STATUS_DELIVERED, $delivery->otp_code, rider: $this->riderUser);

    expect($delivery->fresh()->status)->toBe(Delivery::STATUS_DELIVERED)
        ->and($delivery->fresh()->delivered_at)->toBeTruthy();
});

it('records and broadcasts rider location to active deliveries only', function (): void {
    Event::fake();

    $delivery = app(AssignRiderAction::class)->execute($this->order, $this->rider, $this->manager);
    app(UpdateDeliveryStatusAction::class)->execute($delivery, Delivery::STATUS_PICKED_UP, rider: $this->riderUser);

    $response = $this->actingAs($this->riderUser, 'staff')->postJson('/rider/location', [
        'lat' => 23.7925,
        'lng' => 90.4078,
        'accuracy' => 12,
    ]);

    $response->assertOk();

    // Throttled to one per 5 seconds.
    $this->actingAs($this->riderUser, 'staff')->postJson('/rider/location', [
        'lat' => 23.7930,
        'lng' => 90.4080,
    ])->assertStatus(429);

    $location = $this->rider->fresh()->latestLocation();

    expect($location)->toBeTruthy()
        ->and((float) $location->latitude)->toBe(23.7925);

    Event::assertDispatched(RiderLocationUpdated::class, fn ($event) => $event->orderIds === [$this->order->id]);
});

it('shows the rider only their own deliveries', function (): void {
    $mine = app(AssignRiderAction::class)->execute($this->order, $this->rider, $this->manager);

    $otherOrder = app(CreateOrderAction::class)->execute(
        branch: $this->branch,
        type: OrderType::Delivery,
        lines: [['menu_item_id' => $this->item->id, 'quantity' => 1]],
        channel: OrderChannel::Website,
        terminalCode: 'WEB',
        deliveryAddress: 'Other house',
    );

    app(AssignRiderAction::class)->execute($otherOrder, $this->otherRider, $this->manager);

    $response = $this->actingAs($this->riderUser, 'staff')->get('/rider');

    $response->assertOk();

    $invoices = collect($response->viewData('page')['props']['deliveries'])->map(fn ($delivery) => $delivery['order']['invoice_no']);

    expect($invoices)->toContain($this->order->invoice_no)
        ->and($invoices)->not->toContain($otherOrder->invoice_no);

    // The rider API enforces the same rule.
    $token = $this->riderUser->createToken('rider', ['staff'])->plainTextToken;

    $api = $this->withToken($token)->getJson('/api/v1/rider/deliveries');

    $api->assertOk();
    expect(collect($api->json('data')))->toHaveCount(1);

    // Updating another rider's delivery returns 404.
    $otherDelivery = Delivery::query()->where('order_id', $otherOrder->id)->first();

    $this->withToken($token)->postJson("/api/v1/rider/deliveries/{$otherDelivery->id}/status", [
        'status' => 'picked_up',
    ])->assertNotFound();
});

it('expires the tracking link one hour after delivery', function (): void {
    $delivery = app(AssignRiderAction::class)->execute($this->order, $this->rider, $this->manager, withOtp: false);

    app(UpdateDeliveryStatusAction::class)->execute($delivery, Delivery::STATUS_PICKED_UP, rider: $this->riderUser);
    app(UpdateDeliveryStatusAction::class)->execute($delivery->fresh(), Delivery::STATUS_ON_THE_WAY, rider: $this->riderUser);
    app(UpdateDeliveryStatusAction::class)->execute($delivery->fresh(), Delivery::STATUS_DELIVERED, rider: $this->riderUser);

    // Still visible right after delivery.
    $this->get("/track/{$this->order->tracking_token}")->assertOk();

    // Expired after an hour.
    $delivery->fresh()->update(['delivered_at' => now()->subHours(2)]);

    $this->get("/track/{$this->order->tracking_token}")->assertNotFound();
});

it('summarizes COD and reconciles it once', function (): void {
    $delivery = app(AssignRiderAction::class)->execute($this->order, $this->rider, $this->manager, withOtp: false);

    app(UpdateDeliveryStatusAction::class)->execute($delivery, Delivery::STATUS_PICKED_UP, rider: $this->riderUser);
    app(UpdateDeliveryStatusAction::class)->execute($delivery->fresh(), Delivery::STATUS_ON_THE_WAY, rider: $this->riderUser);
    app(UpdateDeliveryStatusAction::class)->execute($delivery->fresh(), Delivery::STATUS_DELIVERED, rider: $this->riderUser);

    $token = $this->riderUser->createToken('rider', ['staff'])->plainTextToken;

    $summary = $this->withToken($token)->getJson('/api/v1/rider/cod-summary')->assertOk()->json('data');

    expect($summary['count'])->toBe(1)
        ->and($summary['total'])->toBe((string) $this->order->total);

    app(UpdateDeliveryStatusAction::class)->reconcileCod($delivery->fresh(), $this->manager);

    expect(fn () => app(UpdateDeliveryStatusAction::class)->reconcileCod($delivery->fresh(), $this->manager))
        ->toThrow(ValidationException::class);
});

it('guards the dispatch board and rider view by role', function (): void {
    $cashier = User::factory()->role('cashier', $this->branch->id)->create();

    $this->actingAs($cashier, 'staff')->get('/admin/delivery')->assertForbidden();

    // A cashier without a rider profile cannot open the rider view.
    $this->actingAs($cashier, 'staff')->get('/rider')->assertNotFound();

    $this->actingAs($this->manager, 'staff')->get('/admin/delivery')->assertOk();
    $this->actingAs($this->riderUser, 'staff')->get('/rider')->assertOk();
});
