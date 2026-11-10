<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Models\AggregatorChannel;
use App\Models\Branch;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;
use App\Services\Aggregators\Foodpanda\FoodpandaAdapter;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->seed([ChartOfAccountsSeeder::class, RoleSeeder::class]);
    Cache::flush();

    $this->branch = Branch::factory()->create(['code' => 'BR01', 'vat_percent' => '0.00', 'service_charge_percent' => '0.00']);
    $this->owner = User::factory()->role('owner')->create();

    $category = MenuCategory::factory()->create(['branch_id' => $this->branch->id]);
    $this->item = MenuItem::factory()->create([
        'branch_id' => $this->branch->id,
        'menu_category_id' => $category->id,
        'base_price' => '300.00',
        'aggregator_sku' => 'KACCHI-1',
        'is_available' => true,
    ]);

    $this->channel = AggregatorChannel::query()->create([
        'key' => 'foodpanda',
        'name' => 'Foodpanda',
        'commission_percent' => '20.00',
        'webhook_secret' => 'portal-token',
        'environment' => 'sandbox',
        'chain_id' => 'chain-1',
        'client_id' => 'client-1',
        'client_secret' => 'shh',
        'vendor_map' => [(string) $this->branch->id => 'vendor-9'],
        'is_active' => true,
    ]);

    Http::fake([
        '*/v2/oauth/token' => Http::response(['access_token' => 'tok-123', 'expires_in' => 7200]),
    ]);
});

function foodpandaWebhook(array $overrides = []): array
{
    return array_replace_recursive([
        'order_id' => '9d4a63b5-3e07-4440-96af-aa04797da3a0',
        'order_code' => 'wxfr-2440',
        'order_type' => 'DELIVERY',
        'transport_type' => 'LOGISTICS_DELIVERY',
        'status' => 'RECEIVED',
        'comment' => 'Ring the bell',
        'client' => ['id' => 'vendor-9', 'store_id' => 'nbet', 'chain_id' => 'chain-1'],
        'customer' => ['first_name' => '***', 'last_name' => '***', 'delivery_address' => ['street' => 'Evergr******', 'city' => 'Dhaka']],
        'items' => [[
            'sku' => 'KACCHI-1', 'name' => 'Kacchi', 'status' => 'IN_CART',
            'pricing' => ['quantity' => 2, 'unit_price' => 300],
        ]],
        'payment' => ['type' => 'PAID', 'order_total' => 612.0, 'delivery_fee' => 12],
    ], $overrides);
}

it('creates an internal order from a Foodpanda webhook and keeps it idempotent', function (): void {
    $this->postJson('/webhooks/aggregators/foodpanda', foodpandaWebhook(), ['Authorization' => 'portal-token'])->assertOk();

    $order = Order::query()->where('aggregator_order_id', '9d4a63b5-3e07-4440-96af-aa04797da3a0')->firstOrFail();

    expect($order->items)->toHaveCount(1)
        ->and($order->items->first()->quantity)->toBe(2)
        ->and((float) $order->subtotal)->toBe(600.0)
        ->and((float) $order->commission_amount)->toBe(120.0)
        ->and($order->aggregator_meta['order_code'])->toBe('wxfr-2440')
        ->and($order->aggregator_meta['transport_type'])->toBe('LOGISTICS_DELIVERY')
        ->and($order->aggregator_meta['platform_total'])->toEqual(612)
        ->and($order->note)->toContain('wxfr-2440');

    $this->postJson('/webhooks/aggregators/foodpanda', foodpandaWebhook(), ['Authorization' => 'Bearer portal-token'])->assertOk();

    expect(Order::query()->count())->toBe(1);
});

it('rejects a Foodpanda webhook with the wrong token', function (): void {
    $this->postJson('/webhooks/aggregators/foodpanda', foodpandaWebhook(), ['Authorization' => 'nope'])->assertStatus(400);

    expect(Order::query()->count())->toBe(0);
});

it('acknowledges an order from an unmapped outlet and records the problem on the channel', function (): void {
    $this->postJson('/webhooks/aggregators/foodpanda', foodpandaWebhook(['client' => ['id' => 'other']]), ['Authorization' => 'portal-token'])->assertOk();

    expect(Order::query()->count())->toBe(0)
        ->and($this->channel->fresh()->last_error)->toContain('not mapped');
});

it('cancels a pending order when the platform cancels it', function (): void {
    $this->postJson('/webhooks/aggregators/foodpanda', foodpandaWebhook(), ['Authorization' => 'portal-token'])->assertOk();

    $this->postJson('/webhooks/aggregators/foodpanda', foodpandaWebhook([
        'status' => 'CANCELLED',
        'cancellation' => ['cancelled_by' => 'CUSTOMER', 'reason' => 'ITEM_UNAVAILABLE'],
    ]), ['Authorization' => 'portal-token'])->assertOk();

    $order = Order::query()->firstOrFail();

    expect($order->status)->toBe(OrderStatus::Void)
        ->and($order->aggregator_meta['platform_status'])->toBe('CANCELLED')
        ->and($order->aggregator_meta['cancelled_by'])->toBe('CUSTOMER');
});

it('pushes READY_FOR_PICKUP to Foodpanda with a cached bearer token when the kitchen marks the order ready', function (): void {
    Http::fake([
        '*/v2/chains/chain-1/orders/*' => Http::response(['status' => 'READY_FOR_PICKUP']),
    ]);

    $this->postJson('/webhooks/aggregators/foodpanda', foodpandaWebhook(), ['Authorization' => 'portal-token'])->assertOk();
    $order = Order::query()->firstOrFail();

    $this->actingAs($this->owner, 'staff')
        ->patch("/admin/orders/{$order->id}/status", ['status' => 'ready'])
        ->assertSessionHasNoErrors();

    Http::assertSent(fn ($request) => $request->method() === 'PUT'
        && str_contains($request->url(), 'sandbox.partner.deliveryhero.io/v2/chains/chain-1/orders/9d4a63b5-3e07-4440-96af-aa04797da3a0')
        && $request->hasHeader('Authorization', 'Bearer tok-123')
        && $request['status'] === 'READY_FOR_PICKUP'
        && $request['items'][0]['sku'] === 'KACCHI-1'
        && $request['items'][0]['pricing']['quantity'] === 2);

    expect($order->fresh()->aggregator_meta['platform_status'])->toBe('READY_FOR_PICKUP');
});

it('uses DISPATCHED for orders the restaurant delivers itself', function (): void {
    Http::fake(['*/v2/chains/chain-1/orders/*' => Http::response([])]);

    $this->postJson('/webhooks/aggregators/foodpanda', foodpandaWebhook(['transport_type' => 'VENDOR_DELIVERY']), ['Authorization' => 'portal-token'])->assertOk();
    $order = Order::query()->firstOrFail();

    $ok = app(FoodpandaAdapter::class, ['channel' => $this->channel])->pushStatus($order, 'ready');

    expect($ok)->toBeTrue();
    Http::assertSent(fn ($request) => $request->method() === 'PUT' && ($request['status'] ?? null) === 'DISPATCHED');
});

it('cancels on the platform with a reason when the restaurant rejects the order', function (): void {
    Http::fake(['*/v2/chains/chain-1/orders/*' => Http::response([])]);

    $this->postJson('/webhooks/aggregators/foodpanda', foodpandaWebhook(), ['Authorization' => 'portal-token'])->assertOk();
    $order = Order::query()->firstOrFail();

    $this->actingAs($this->owner, 'staff')
        ->post("/admin/aggregators/orders/{$order->id}/decision", ['decision' => 'reject', 'reason' => 'TOO_BUSY'])
        ->assertSessionHasNoErrors();

    Http::assertSent(fn ($request) => $request->method() === 'PUT'
        && $request['status'] === 'CANCELLED'
        && $request['cancellation']['reason'] === 'TOO_BUSY');

    expect($order->fresh()->status)->toBe(OrderStatus::Void);
});

it('keeps the kitchen working and records the error when the platform call fails', function (): void {
    Http::fake(['*/v2/chains/chain-1/orders/*' => Http::response(['error' => 'boom'], 500)]);

    $this->postJson('/webhooks/aggregators/foodpanda', foodpandaWebhook(), ['Authorization' => 'portal-token'])->assertOk();
    $order = Order::query()->firstOrFail();

    $this->actingAs($this->owner, 'staff')
        ->patch("/admin/orders/{$order->id}/status", ['status' => 'ready'])
        ->assertSessionHasNoErrors();

    expect($order->fresh()->status)->toBe(OrderStatus::Ready)
        ->and($this->channel->fresh()->last_error)->not->toBeNull();
});

it('syncs prices and availability to every mapped outlet', function (): void {
    Http::fake(['*/v2/chains/chain-1/vendors/vendor-9/catalog' => Http::response(['job_id' => 'j1', 'job_status' => 'QUEUED'], 202)]);

    MenuItem::factory()->create([
        'branch_id' => $this->branch->id,
        'menu_category_id' => $this->item->menu_category_id,
        'base_price' => '90.00',
        'is_available' => false,
    ]);

    $this->actingAs($this->owner, 'staff')
        ->post("/admin/aggregators/channels/{$this->channel->id}/menu-sync")
        ->assertSessionHasNoErrors();

    Http::assertSent(function ($request): bool {
        if ($request->method() !== 'PUT' || ! str_contains($request->url(), '/vendors/vendor-9/catalog')) {
            return false;
        }

        $products = collect($request['products']);

        return $products->count() === 2
            && $products->firstWhere('sku', 'KACCHI-1')['price'] === 300.0
            && $products->firstWhere('sku', 'KACCHI-1')['active'] === true
            && $products->contains(fn ($product): bool => $product['active'] === false && str_starts_with($product['sku'], 'DT-'));
    });

    expect($this->channel->fresh()->last_synced_at)->not->toBeNull();
});

it('opens and closes an outlet through the platform', function (): void {
    Http::fake(['*/v2/chains/chain-1/vendors/vendor-9/status' => Http::response([])]);

    $this->actingAs($this->owner, 'staff')
        ->post("/admin/aggregators/channels/{$this->channel->id}/outlet-status", [
            'branch_id' => $this->branch->id,
            'status' => 'CLOSED_TODAY',
            'closed_reason' => 'TOO_BUSY_KITCHEN',
        ])->assertSessionHasNoErrors();

    Http::assertSent(fn ($request) => $request->method() === 'PUT'
        && $request['status'] === 'CLOSED_TODAY'
        && $request['closed_reason'] === 'TOO_BUSY_KITCHEN'
        && ! isset($request['closed_until']));
});

it('tests the connection and reports a failure clearly', function (): void {
    $this->actingAs($this->owner, 'staff')
        ->post("/admin/aggregators/channels/{$this->channel->id}/connection-test")
        ->assertSessionHas('success');

    Cache::flush();
    Http::swap(new Factory);
    Http::fake(['*/v2/oauth/token' => Http::response(['error' => 'invalid_client'], 401)]);

    $this->actingAs($this->owner, 'staff')
        ->post("/admin/aggregators/channels/{$this->channel->id}/connection-test")
        ->assertSessionHas('error');

    expect($this->channel->fresh()->last_error)->not->toBeNull();
});

it('imports orders the webhook missed from the order history', function (): void {
    Http::fake([
        '*/v2/chains/chain-1/vendors/vendor-9/orders*' => Http::response(['orders' => [foodpandaWebhook(), foodpandaWebhook(['order_id' => 'delivered-1', 'status' => 'DELIVERED'])]]),
    ]);

    $this->actingAs($this->owner, 'staff')
        ->post("/admin/aggregators/channels/{$this->channel->id}/import-orders")
        ->assertSessionHas('success');

    expect(Order::query()->count())->toBe(1);
});

it('never exposes the client secret to the browser', function (): void {
    $page = $this->actingAs($this->owner, 'staff')->get('/admin/aggregators')->viewData('page');
    $channel = collect($page['props']['channels'])->firstWhere('key', 'foodpanda');

    expect($channel)->not->toHaveKey('client_secret')
        ->and($channel['has_client_secret'])->toBeTrue()
        ->and($channel['vendor_map'])->toBe([(string) $this->branch->id => 'vendor-9']);
});
