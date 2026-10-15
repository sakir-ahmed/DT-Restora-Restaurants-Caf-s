<?php

declare(strict_types=1);

use App\Actions\Sales\CompleteGatewayPaymentAction;
use App\Actions\Sales\CreateOrderAction;
use App\Actions\Sales\PayOrderAction;
use App\Actions\Sales\StartGatewayPaymentAction;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\Branch;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Payment;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->seed([ChartOfAccountsSeeder::class, RoleSeeder::class]);

    config(['services.bkash.app_key' => 'test-key', 'services.bkash.app_secret' => 'test-secret',
        'services.bkash.username' => 'test-user', 'services.bkash.password' => 'test-pass',
        'services.bkash.base_url' => 'https://tokenized.pay.bka.sh/v1.2.0-beta']);

    $this->branch = Branch::factory()->create(['code' => 'BR01']);
    $this->cashier = User::factory()->role('cashier', $this->branch->id)->create();
    $this->manager = User::factory()->role('branch-manager', $this->branch->id)->create();

    $category = MenuCategory::factory()->create(['branch_id' => $this->branch->id]);
    $this->item = MenuItem::factory()->create([
        'branch_id' => $this->branch->id,
        'menu_category_id' => $category->id,
        'base_price' => '400.00',
    ]);

    $this->order = app(CreateOrderAction::class)->execute(
        branch: $this->branch,
        type: OrderType::Takeaway,
        lines: [['menu_item_id' => $this->item->id, 'quantity' => 1]],
        cashier: $this->cashier,
    );
});

it('starts a bkash payment and stores a pending row', function (): void {
    Http::fake([
        '*/token/grant' => Http::response(['id_token' => 'grant-token']),
        '*/checkout/create' => Http::response([
            'statusCode' => '0000',
            'paymentID' => 'PM-123',
            'bkashURL' => 'https://pay.bka.sh/abc',
        ]),
    ]);

    $result = app(StartGatewayPaymentAction::class)->execute($this->order, 'bkash', 'IDEM-1');

    expect($result['redirect_url'])->toBe('https://pay.bka.sh/abc')
        ->and($result['payment']->status)->toBe(Payment::STATUS_PENDING)
        ->and($result['payment']->gateway)->toBe('bkash')
        ->and($result['payment']->gateway_payment_id)->toBe('PM-123');
});

it('never double-pays on duplicate callbacks', function (): void {
    Http::fake([
        '*/token/grant' => Http::response(['id_token' => 'grant-token']),
        '*/checkout/execute' => Http::response(['statusCode' => '0000', 'paymentID' => 'PM-123']),
        '*/payment/status' => Http::response([
            'paymentID' => 'PM-123',
            'transactionStatus' => 'Completed',
            'amount' => (string) $this->order->total,
            'trxID' => 'TRX-9',
        ]),
    ]);

    $payment = Payment::query()->create([
        'order_id' => $this->order->id,
        'method' => 'bkash',
        'amount' => (string) $this->order->total,
        'status' => Payment::STATUS_PENDING,
        'gateway' => 'bkash',
        'gateway_payment_id' => 'PM-123',
    ]);

    $first = app(CompleteGatewayPaymentAction::class)->verifyAndCompleteBkash($payment);
    $second = app(CompleteGatewayPaymentAction::class)->verifyAndCompleteBkash($payment->fresh());

    expect($first->status)->toBe(Payment::STATUS_COMPLETED)
        ->and($second->status)->toBe(Payment::STATUS_COMPLETED)
        ->and($this->order->fresh()->status)->toBe(OrderStatus::Paid)
        // One grant, one execute, one status query, one trx lookup; the
        // duplicate callback returns early without touching the gateway.
        ->and(Http::assertSentCount(4));
});

it('rejects a payment when the gateway amount does not match', function (): void {
    Http::fake([
        '*/token/grant' => Http::response(['id_token' => 'grant-token']),
        '*/checkout/execute' => Http::response(['statusCode' => '0000']),
        '*/payment/status' => Http::response([
            'transactionStatus' => 'Completed',
            'amount' => '99.00', // paid the wrong amount
            'trxID' => 'TRX-1',
        ]),
    ]);

    $payment = Payment::query()->create([
        'order_id' => $this->order->id,
        'method' => 'bkash',
        'amount' => (string) $this->order->total,
        'status' => Payment::STATUS_PENDING,
        'gateway' => 'bkash',
        'gateway_payment_id' => 'PM-123',
    ]);

    $result = app(CompleteGatewayPaymentAction::class)->verifyAndCompleteBkash($payment);

    expect($result->status)->toBe(Payment::STATUS_FAILED)
        ->and($this->order->fresh()->status)->not->toBe(OrderStatus::Paid);
});

it('is idempotent by idempotency key within a day', function (): void {
    Http::fake([
        '*/token/grant' => Http::response(['id_token' => 'grant-token']),
        '*/checkout/create' => Http::response([
            'statusCode' => '0000',
            'paymentID' => 'PM-'.uniqid(),
            'bkashURL' => 'https://pay.bka.sh/abc',
        ]),
    ]);

    $action = app(StartGatewayPaymentAction::class);

    $first = $action->execute($this->order, 'bkash', 'IDEM-SAME');
    $second = $action->execute($this->order, 'bkash', 'IDEM-SAME');

    expect(Payment::query()->where('idempotency_key', 'IDEM-SAME')->count())->toBe(1);
});

it('splits a bill across cash and wallet and completes after approval', function (): void {
    $payments = app(PayOrderAction::class)->executeSplit($this->order, [
        ['method' => 'cash', 'amount' => 200],
        ['method' => 'bkash', 'amount' => (float) $this->order->total - 200, 'reference' => 'TRX-7'],
    ], $this->cashier);

    expect($payments)->toHaveCount(2)
        ->and($payments[0]->status)->toBe(Payment::STATUS_COMPLETED)
        ->and($payments[1]->status)->toBe(Payment::STATUS_PENDING_VERIFICATION)
        ->and($this->order->fresh()->status)->not->toBe(OrderStatus::Paid);

    app(PayOrderAction::class)->approveManual($payments[1], $this->manager);

    expect($payments[1]->fresh()->status)->toBe(Payment::STATUS_COMPLETED)
        ->and($this->order->fresh()->status)->toBe(OrderStatus::Paid);
});

it('rejects a split that does not sum to the total', function (): void {
    expect(fn () => app(PayOrderAction::class)->executeSplit($this->order, [
        ['method' => 'cash', 'amount' => 100],
        ['method' => 'card', 'amount' => 100],
    ], $this->cashier))->toThrow(ValidationException::class);
});

it('records a manual wallet payment through the controller and a manager approves it', function (): void {
    $response = $this->actingAs($this->cashier, 'staff')
        ->post("/admin/orders/{$this->order->id}/manual-payment", [
            'method' => 'bkash',
            'amount' => (string) $this->order->total,
            'reference' => 'TRX-MANUAL-1',
        ]);

    $response->assertRedirect();

    $payment = Payment::query()->where('reference', 'TRX-MANUAL-1')->firstOrFail();

    expect($payment->status)->toBe(Payment::STATUS_PENDING_VERIFICATION);

    // Cashier cannot approve.
    $this->actingAs($this->cashier, 'staff')
        ->post("/admin/payments/{$payment->id}/approve")
        ->assertForbidden();

    $this->actingAs($this->manager, 'staff')
        ->post("/admin/payments/{$payment->id}/approve");

    expect($payment->fresh()->status)->toBe(Payment::STATUS_COMPLETED)
        ->and($this->order->fresh()->status)->toBe(OrderStatus::Paid);
});

it('renders the bkash callback page for duplicates without new side effects', function (): void {
    $payment = Payment::query()->create([
        'order_id' => $this->order->id,
        'method' => 'bkash',
        'amount' => (string) $this->order->total,
        'status' => Payment::STATUS_COMPLETED,
        'gateway' => 'bkash',
        'gateway_payment_id' => 'PM-DUP',
    ]);

    $response = $this->get('/payments/bkash/callback?paymentID=PM-DUP&status=success');

    $response->assertOk();

    expect($payment->fresh()->status)->toBe(Payment::STATUS_COMPLETED)
        ->and(Payment::where('gateway_payment_id', 'PM-DUP')->count())->toBe(1);
});
