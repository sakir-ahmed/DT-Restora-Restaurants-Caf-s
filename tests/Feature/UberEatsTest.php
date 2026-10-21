<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Models\AggregatorChannel;
use App\Models\Branch;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->seed([ChartOfAccountsSeeder::class, RoleSeeder::class]);
    Cache::flush();

    $this->branch = Branch::factory()->create(['code' => 'BR01', 'vat_percent' => '0.00', 'service_charge_percent' => '0.00']);
    $category = MenuCategory::factory()->create(['branch_id' => $this->branch->id]);
    $this->item = MenuItem::factory()->create([
        'branch_id' => $this->branch->id,
        'menu_category_id' => $category->id,
        'base_price' => '300.00',
        'aggregator_sku' => 'KACCHI-1',
        'is_available' => true,
    ]);

    $this->channel = AggregatorChannel::query()->create([
        'key' => 'ubereats', 'name' => 'Uber Eats', 'commission_percent' => '25.00',
        'environment' => 'sandbox', 'client_id' => 'cid', 'client_secret' => 'shh',
        'vendor_map' => [(string) $this->branch->id => 'store-1'], 'is_active' => true,
    ]);

    Http::fake([
        'sandbox-login.uber.com/*' => Http::response(['access_token' => 'tok', 'expires_in' => 2592000]),
        '*/v2/eats/order/ord-1' => Http::response([
            'id' => 'ord-1', 'display_id' => 'AB12C', 'type' => 'DELIVERY_BY_UBER',
            'store' => ['id' => 'store-1'],
            'eater' => ['first_name' => 'Rafi', 'last_name' => 'K'],
            'cart' => ['items' => [['id' => 'x', 'external_data' => 'KACCHI-1', 'title' => 'Kacchi', 'quantity' => 2]], 'special_instructions' => 'No salad'],
            'payment' => ['charges' => ['total' => ['amount' => 60000]]],
        ]),
        '*/v1/eats/orders/*' => Http::response([], 204),
    ]);
});

function uberPost(object $test, array $body, ?string $secret = 'shh')
{
    $raw = json_encode($body);

    return $test->call('POST', '/webhooks/aggregators/ubereats', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_UBER_SIGNATURE' => hash_hmac('sha256', $raw, (string) $secret),
    ], $raw);
}

function uberEvent(string $type = 'orders.notification'): array
{
    return ['event_type' => $type, 'event_id' => 'e1', 'meta' => ['resource_id' => 'ord-1', 'user_id' => 'store-1', 'status' => 'pending']];
}

it('creates an order from a signed webhook and accepts it on Uber', function (): void {
    uberPost($this, uberEvent())->assertOk();

    $order = Order::query()->where('aggregator_order_id', 'ord-1')->firstOrFail();
    expect($order->items->first()->quantity)->toBe(2)
        ->and($order->aggregator_meta['order_code'])->toBe('AB12C')
        ->and($order->aggregator_meta['platform_total'])->toEqual(600)
        ->and($order->aggregator_meta['platform_status'])->toBe('ACCEPTED');

    Http::assertSent(fn (Request $r) => str_contains($r->url(), '/v1/eats/orders/ord-1/accept_pos_order'));
});

it('does not duplicate a repeated webhook', function (): void {
    uberPost($this, uberEvent())->assertOk();
    uberPost($this, uberEvent())->assertOk();

    expect(Order::query()->where('aggregator_order_id', 'ord-1')->count())->toBe(1);
});

it('rejects a bad signature', function (): void {
    uberPost($this, uberEvent(), 'wrong')->assertStatus(401);

    expect(Order::query()->count())->toBe(0);
});

it('denies an order whose items are not on the menu', function (): void {
    $this->item->update(['aggregator_sku' => 'OTHER']);

    uberPost($this, uberEvent())->assertOk();

    expect(Order::query()->count())->toBe(0);
    Http::assertSent(fn (Request $r) => str_contains($r->url(), 'deny_pos_order') && $r['reason']['code'] === 'ITEM_AVAILABILITY');
});

it('cancels a pending order when the customer cancels on Uber', function (): void {
    uberPost($this, uberEvent())->assertOk();
    uberPost($this, uberEvent('orders.cancel'))->assertOk();

    expect(Order::query()->where('aggregator_order_id', 'ord-1')->first()->status)->toBe(OrderStatus::Void);
});
