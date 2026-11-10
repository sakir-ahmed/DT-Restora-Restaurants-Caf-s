<?php

declare(strict_types=1);

use App\Models\Branch;
use App\Models\Customer;
use App\Models\DeviceToken;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemAddOn;
use App\Models\Order;
use App\Models\User;
use App\Services\PushNotificationService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->seed([RoleSeeder::class, ChartOfAccountsSeeder::class]);

    $this->branch = Branch::factory()->create([
        'code' => 'BR01',
        'vat_percent' => '0.00',
        'service_charge_percent' => '0.00',
    ]);
    $this->cashier = User::factory()->role('cashier', $this->branch->id)->create();
    $category = MenuCategory::factory()->create(['branch_id' => $this->branch->id]);
    $this->item = MenuItem::factory()->create([
        'branch_id' => $this->branch->id,
        'menu_category_id' => $category->id,
        'base_price' => '200.00',
    ]);
});

it('publishes the v1 paths in the OpenAPI document', function (): void {
    $document = $this->getJson('/api-docs.json')->assertOk()->json();

    $paths = array_keys($document['paths'] ?? []);
    $joined = implode(' ', $paths);

    expect($joined)->toContain('/auth/login')
        ->and($joined)->toContain('/auth/google')
        ->and($joined)->toContain('/menu')
        ->and($joined)->toContain('/devices')
        ->and($joined)->toContain('/payables')
        ->and($joined)->toContain('/sync/push')
        ->and($joined)->toContain('/payments/bkash/create')
        ->and($joined)->toContain('/payments/sslcommerz/init')
        ->and($joined)->toContain('/aggregators/menu-sync')
        ->and($joined)->toContain('/stock-transfers/{transfer}/approve');
});

it('creates a staff order once for the same idempotency key', function (): void {
    $token = $this->cashier->createToken('staff', ['staff'])->plainTextToken;

    $payload = [
        'order_type' => 'takeaway',
        'items' => [['menu_item_id' => $this->item->id, 'quantity' => 1]],
    ];

    $this->withToken($token)
        ->postJson('/api/v1/branches/'.$this->branch->id.'/orders', $payload)
        ->assertUnprocessable();

    $created = $this->withToken($token)
        ->withHeader('Idempotency-Key', 'order-1')
        ->postJson('/api/v1/branches/'.$this->branch->id.'/orders', $payload);

    $created->assertCreated();

    $this->withToken($token)
        ->withHeader('Idempotency-Key', 'order-1')
        ->postJson('/api/v1/branches/'.$this->branch->id.'/orders', $payload)
        ->assertCreated()
        ->assertJsonPath('data.id', $created->json('data.id'));

    expect(Order::query()->count())->toBe(1);
});

it('registers a customer device and sends FCM only when configured', function (): void {
    Http::fake();

    $customer = Customer::query()->create(['name' => 'Nila', 'mobile' => '8801711222333']);
    $token = $customer->createToken('app', ['customer'])->plainTextToken;

    $this->withToken($token)->postJson('/api/v1/customer/devices', [
        'token' => 'device-token-1',
        'platform' => 'android',
    ])->assertCreated();

    expect(DeviceToken::query()->count())->toBe(1);

    app(PushNotificationService::class)->notifyCustomer($customer, 'Ready', 'Your order is ready');
    Http::assertNothingSent();

    config(['services.fcm.server_key' => 'test-key']);
    app(PushNotificationService::class)->notifyCustomer($customer, 'Ready', 'Your order is ready');
    Http::assertSentCount(1);
});

it('refuses Google sign-in until credentials exist', function (): void {
    config(['services.google.client_id' => null]);

    $this->postJson('/api/v1/auth/google', ['token' => 'abc'])->assertStatus(503);
});

it('refuses a bKash payment until the gateway is configured', function (): void {
    config([
        'services.bkash.app_key' => null,
        'services.bkash.app_secret' => null,
        'services.bkash.username' => null,
        'services.bkash.password' => null,
    ]);

    $order = Order::query()->create([
        'branch_id' => $this->branch->id,
        'invoice_no' => 'BR01-API-0001',
        'order_type' => 'takeaway',
        'channel' => 'pos',
        'status' => 'pending',
        'subtotal' => '200.00',
        'total' => '200.00',
    ]);

    $token = $this->cashier->createToken('staff', ['staff'])->plainTextToken;

    $this->withToken($token)
        ->withHeader('Idempotency-Key', 'pay-1')
        ->postJson('/api/v1/payments/bkash/create', ['order_id' => $order->id])
        ->assertStatus(503);
});

it('returns receipt details on a staff order', function (): void {
    $token = $this->cashier->createToken('staff', ['staff'])->plainTextToken;

    $created = $this->withToken($token)
        ->withHeader('Idempotency-Key', 'receipt-1')
        ->postJson('/api/v1/branches/'.$this->branch->id.'/orders', [
            'order_type' => 'takeaway',
            'items' => [['menu_item_id' => $this->item->id, 'quantity' => 2]],
        ])
        ->assertCreated();

    $order = $this->withToken($token)
        ->getJson('/api/v1/branches/'.$this->branch->id.'/orders/'.$created->json('data.id'))
        ->assertOk()
        ->json('data');

    expect($order)->toHaveKeys(['subtotal', 'discount_amount', 'service_charge', 'vat_amount', 'created_at', 'cashier', 'payments', 'void_status'])
        ->and($order['subtotal'])->toBe('400.00')
        ->and($order['items'][0]['unit_price'])->toBe('200.00')
        ->and($order['cashier'])->toBe($this->cashier->name);
});

it('prices add-ons chosen on a staff order and returns them', function (): void {
    $addOn = MenuItemAddOn::query()->create([
        'menu_item_id' => $this->item->id,
        'name' => 'Extra cheese',
        'price' => '30.00',
        'is_active' => true,
    ]);
    $token = $this->cashier->createToken('staff', ['staff'])->plainTextToken;

    $created = $this->withToken($token)
        ->withHeader('Idempotency-Key', 'addon-1')
        ->postJson('/api/v1/branches/'.$this->branch->id.'/orders', [
            'order_type' => 'takeaway',
            'items' => [[
                'menu_item_id' => $this->item->id,
                'quantity' => 2,
                'add_ons' => [['id' => $addOn->id, 'qty' => 1]],
            ]],
        ])
        ->assertCreated();

    $line = $created->json('data.items.0');

    expect($line['line_total'])->toBe('460.00')
        ->and($line['add_ons'][0]['name'])->toBe('Extra cheese')
        ->and($line['add_ons'][0]['qty'])->toBe(1);
});
