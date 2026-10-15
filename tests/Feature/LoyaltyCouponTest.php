<?php

declare(strict_types=1);

use App\Actions\Sales\ApproveOrderVoidAction;
use App\Actions\Sales\CreateOrderAction;
use App\Actions\Sales\PayOrderAction;
use App\Actions\Sales\RequestOrderVoidAction;
use App\Enums\OrderChannel;
use App\Enums\OrderType;
use App\Models\Branch;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\LoyaltyEntry;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;
use App\Services\CouponService;
use App\Services\LoyaltyService;
use Database\Seeders\RoleSeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);

    $this->branch = Branch::factory()->create(['code' => 'BR01', 'vat_percent' => '0.00', 'service_charge_percent' => '0.00']);
    $this->owner = User::factory()->role('owner')->create();

    $category = MenuCategory::factory()->create(['branch_id' => $this->branch->id]);
    $this->item = MenuItem::factory()->create([
        'branch_id' => $this->branch->id,
        'menu_category_id' => $category->id,
        'base_price' => '1000.00',
    ]);

    $this->customer = Customer::query()->create(['name' => 'Ayesha', 'mobile' => '8801711000099']);
});

function paidOrderFor($test, ?Customer $customer = null): Order
{
    $order = app(CreateOrderAction::class)->execute(
        branch: $test->branch,
        type: OrderType::Takeaway,
        lines: [['menu_item_id' => $test->item->id, 'quantity' => 1]],
        customer: $customer ?? $test->customer,
    );

    app(PayOrderAction::class)->executeCash($order, 1000);

    return $order->fresh();
}

it('earns points only on paid orders, excluding VAT, service and delivery', function (): void {
    $order = paidOrderFor($this);

    // 1000 taka after discount => 10 points at the default rate.
    $entry = LoyaltyEntry::query()->where('order_id', $order->id)->first();

    expect($entry)->not->toBeNull()
        ->and($entry->type)->toBe('earned')
        ->and($entry->points)->toBe(10)
        ->and($entry->expires_at)->not->toBeNull()
        ->and(app(LoyaltyService::class)->balance($this->customer))->toBe(10);
});

it('keeps the ledger balance equal to the sum of entries', function (): void {
    $loyalty = app(LoyaltyService::class);

    paidOrderFor($this); // +10
    paidOrderFor($this); // +10
    $loyalty->redeem($this->customer, 15); // -15
    $loyalty->adjust($this->customer, 5, 'Goodwill', $this->owner); // +5

    $sum = (int) LoyaltyEntry::query()->where('customer_id', $this->customer->id)->sum('points');
    $balance = $loyalty->balance($this->customer);

    expect($sum)->toBe(10)
        ->and($balance)->toBe(10)
        ->and($balance)->toBe($sum);
});

it('reverses points when an order is voided', function (): void {
    $order = paidOrderFor($this);

    expect(app(LoyaltyService::class)->balance($this->customer))->toBe(10);

    $request = app(RequestOrderVoidAction::class)->execute($order, User::factory()->role('cashier', $this->branch->id)->create(), 'wrong_item');

    app(ApproveOrderVoidAction::class)->execute($request, $this->owner);

    expect(app(LoyaltyService::class)->balance($this->customer))->toBe(0)
        ->and(LoyaltyEntry::query()->where('order_id', $order->id)->count())->toBe(2); // earned + reversal
});

it('rejects redemption beyond the balance', function (): void {
    paidOrderFor($this);

    expect(fn () => app(LoyaltyService::class)->redeem($this->customer, 50))
        ->toThrow(ValidationException::class);
});

it('expires only unredeemed points, FIFO, and never twice', function (): void {
    $loyalty = app(LoyaltyService::class);

    LoyaltyEntry::query()->create([
        'customer_id' => $this->customer->id,
        'type' => 'earned',
        'points' => 20,
        'balance_after' => 20,
        'expires_at' => now()->subDay(),
    ]);

    LoyaltyEntry::query()->create([
        'customer_id' => $this->customer->id,
        'type' => 'redeemed',
        'points' => -12,
        'balance_after' => 8,
        'note' => 'partially used',
    ]);

    $count = $loyalty->expireDue();
    $again = $loyalty->expireDue();

    expect($count)->toBe(1)
        ->and($again)->toBe(0)
        ->and($loyalty->balance($this->customer))->toBe(0); // 20 - 12 redeemed - 8 expired
});

it('applies a coupon and records the redemption exactly once', function (): void {
    $coupon = Coupon::query()->create([
        'code' => 'SAVE10',
        'type' => 'percent',
        'value' => '10.00',
        'is_active' => true,
    ]);

    $order = app(CreateOrderAction::class)->execute(
        branch: $this->branch,
        type: OrderType::Takeaway,
        lines: [['menu_item_id' => $this->item->id, 'quantity' => 1]],
        customer: $this->customer,
        couponCode: 'save10',
    );

    expect((float) $order->discount_amount)->toBe(100.0)
        ->and((float) $order->total)->toBe(900.0)
        ->and($order->coupon_id)->toBe($coupon->id)
        ->and($coupon->redemptions()->count())->toBe(1);
});

it('cannot bypass the total usage limit with repeated attempts', function (): void {
    $coupon = Coupon::query()->create([
        'code' => 'ONEONLY',
        'type' => 'fixed',
        'value' => '50.00',
        'usage_limit_total' => 1,
        'is_active' => true,
    ]);

    $action = app(CreateOrderAction::class);

    $first = $action->execute(
        branch: $this->branch,
        type: OrderType::Takeaway,
        lines: [['menu_item_id' => $this->item->id, 'quantity' => 1]],
        couponCode: 'ONEONLY',
    );

    // Second attempt with the same code must be rejected.
    expect(fn () => $action->execute(
        branch: $this->branch,
        type: OrderType::Takeaway,
        lines: [['menu_item_id' => $this->item->id, 'quantity' => 1]],
        couponCode: 'ONEONLY',
    ))->toThrow(ValidationException::class);

    // Direct concurrent redemption attempt on the same coupon row.
    $second = $action->execute(
        branch: $this->branch,
        type: OrderType::Takeaway,
        lines: [['menu_item_id' => $this->item->id, 'quantity' => 1]],
    );

    expect(fn () => app(CouponService::class)->redeem($coupon->fresh(), $second))
        ->toThrow(ValidationException::class)
        ->and($coupon->redemptions()->count())->toBe(1);
});

it('enforces per-customer and channel and branch rules', function (): void {
    Coupon::query()->create([
        'code' => 'WEBONLY',
        'type' => 'fixed',
        'value' => '30.00',
        'channels' => ['website'],
        'is_active' => true,
    ]);

    $action = app(CreateOrderAction::class);

    // POS channel is rejected.
    expect(fn () => $action->execute(
        branch: $this->branch,
        type: OrderType::Takeaway,
        lines: [['menu_item_id' => $this->item->id, 'quantity' => 1]],
        couponCode: 'WEBONLY',
    ))->toThrow(ValidationException::class);

    // Website channel is accepted.
    $action->execute(
        branch: $this->branch,
        type: OrderType::Takeaway,
        lines: [['menu_item_id' => $this->item->id, 'quantity' => 1]],
        customer: $this->customer,
        channel: OrderChannel::Website,
        couponCode: 'WEBONLY',
    );

    expect(Coupon::query()->where('code', 'WEBONLY')->first()->redemptions()->count())->toBe(1);
});

it('shows the loyalty admin page to permitted roles only', function (): void {
    $manager = User::factory()->role('branch-manager', $this->branch->id)->create();

    $this->actingAs($manager, 'staff')->get('/admin/loyalty')->assertOk();

    $waiter = User::factory()->role('waiter', $this->branch->id)->create();
    $this->actingAs($waiter, 'staff')->get('/admin/loyalty')->assertForbidden();

    $this->actingAs($this->owner, 'staff')->get('/admin/coupons')->assertOk();
    $this->actingAs($waiter, 'staff')->get('/admin/coupons')->assertForbidden();
});
