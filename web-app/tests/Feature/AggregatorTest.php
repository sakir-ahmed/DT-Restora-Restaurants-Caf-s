<?php

declare(strict_types=1);

use App\Models\AggregatorChannel;
use App\Models\Branch;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;
use App\Services\Metrics\MetricsService;
use Database\Seeders\RoleSeeder;

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);

    $this->branch = Branch::factory()->create(['code' => 'BR01', 'vat_percent' => '0.00', 'service_charge_percent' => '0.00']);
    $this->owner = User::factory()->role('owner')->create();

    $category = MenuCategory::factory()->create(['branch_id' => $this->branch->id]);
    $this->item = MenuItem::factory()->create([
        'branch_id' => $this->branch->id,
        'menu_category_id' => $category->id,
        'base_price' => '300.00',
    ]);

    $this->channel = AggregatorChannel::query()->create([
        'key' => 'foodpanda',
        'name' => 'Foodpanda',
        'commission_percent' => '25.00',
        'webhook_secret' => 'secret-token',
        'is_active' => true,
    ]);
});

function webhookPayload($test): array
{
    return [
        'order' => [
            'platform_order_id' => 'FP-'.uniqid(),
            'branch_id' => $test->branch->id,
            'customer_name' => 'Platform Customer',
            'customer_mobile' => '01711000066',
            'address' => 'Platform address 12',
            'items' => [
                ['menu_item_id' => $test->item->id, 'quantity' => 2],
            ],
        ],
    ];
}

it('creates an order from a signed webhook with commission recorded', function (): void {
    $response = $this->postJson('/webhooks/aggregators/foodpanda', webhookPayload($this), [
        'X-Webhook-Signature' => 'secret-token',
    ]);

    $response->assertOk();

    $order = Order::query()->whereNotNull('aggregator_order_id')->firstOrFail();

    expect($order->channel->value)->toBe('aggregator')
        ->and($order->commission_amount)->toBe('150.00') // 25% of 600
        ->and($order->aggregator_channel_id)->toBe($this->channel->id);
});

it('acknowledges duplicate webhooks without creating a second order', function (): void {
    $payload = webhookPayload($this);

    $this->postJson('/webhooks/aggregators/foodpanda', $payload, ['X-Webhook-Signature' => 'secret-token'])->assertOk();
    $this->postJson('/webhooks/aggregators/foodpanda', $payload, ['X-Webhook-Signature' => 'secret-token'])->assertOk();

    expect(Order::query()->whereNotNull('aggregator_order_id')->count())->toBe(1);
});

it('rejects webhooks with an invalid signature', function (): void {
    $this->postJson('/webhooks/aggregators/foodpanda', webhookPayload($this), [
        'X-Webhook-Signature' => 'wrong',
    ])->assertStatus(400);

    expect(Order::count())->toBe(0);
});

it('rejects unknown channels and malformed payloads', function (): void {
    $this->postJson('/webhooks/aggregators/unknown', webhookPayload($this))->assertStatus(400);

    $this->postJson('/webhooks/aggregators/foodpanda', ['order' => ['platform_order_id' => 'X']], [
        'X-Webhook-Signature' => 'secret-token',
    ])->assertStatus(400);
});

it('shows aggregator commission in the profit report', function (): void {
    $this->postJson('/webhooks/aggregators/foodpanda', webhookPayload($this), ['X-Webhook-Signature' => 'secret-token']);

    $summary = app(MetricsService::class)->summary($this->branch->id, now()->startOfDay(), now()->endOfDay());

    expect($summary['commission'])->toBe('150.00');
});

it('creates manual aggregator orders through the admin screen', function (): void {
    $response = $this->actingAs($this->owner, 'staff')->post('/admin/aggregators/orders/manual', [
        'aggregator_channel_id' => $this->channel->id,
        'platform_order_id' => 'MANUAL-1',
        'branch_id' => $this->branch->id,
        'customer_name' => 'Walkover',
        'address' => 'Somewhere',
        'items' => [['menu_item_id' => $this->item->id, 'quantity' => 1]],
    ]);

    $response->assertRedirect();

    $order = Order::query()->where('aggregator_order_id', 'MANUAL-1')->firstOrFail();

    expect((float) $order->total)->toBe(300.0)
        ->and($order->commission_amount)->toBe('75.00');
});

it('guards aggregator management by permission', function (): void {
    $manager = User::factory()->role('branch-manager', $this->branch->id)->create();
    $cashier = User::factory()->role('cashier', $this->branch->id)->create();

    $this->actingAs($manager, 'staff')->get('/admin/aggregators')->assertOk();
    $this->actingAs($cashier, 'staff')->get('/admin/aggregators')->assertForbidden();
});
