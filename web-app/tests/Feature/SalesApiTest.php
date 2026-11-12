<?php

declare(strict_types=1);

use App\Enums\OrderChannel;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Preorder;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function (): void {
    $this->seed([RoleSeeder::class, ChartOfAccountsSeeder::class]);

    $this->branch = Branch::factory()->create(['code' => 'BR01', 'vat_percent' => '0.00', 'service_charge_percent' => '0.00']);
    $this->owner = User::factory()->role('owner')->create();
    $this->token = $this->owner->createToken('staff', ['staff'])->plainTextToken;
    $category = MenuCategory::factory()->create(['branch_id' => $this->branch->id]);
    $this->item = MenuItem::factory()->create([
        'branch_id' => $this->branch->id,
        'menu_category_id' => $category->id,
        'base_price' => '200.00',
    ]);
    $this->base = '/api/v1/branches/'.$this->branch->id;
});

function createApiOrder($test, string $key = 'o1'): int
{
    return $test->withToken($test->token)
        ->withHeader('Idempotency-Key', $key)
        ->postJson($test->base.'/orders', [
            'order_type' => 'takeaway',
            'customer_mobile' => '01712345678',
            'items' => [['menu_item_id' => $test->item->id, 'quantity' => 1]],
        ])->assertCreated()->json('data.id');
}

it('puts an order on due, lists it and collects the balance', function (): void {
    $id = createApiOrder($this);

    $this->withToken($this->token)->withHeader('Idempotency-Key', 'd1')
        ->postJson($this->base.'/orders/'.$id.'/due', ['lines' => [['method' => 'cash', 'amount' => 50]]])
        ->assertOk()
        ->assertJsonPath('data.balance', '150.00');

    $list = $this->withToken($this->token)->getJson($this->base.'/dues')->assertOk();
    expect($list->json('data.dues'))->toHaveCount(1)
        ->and($list->json('data.outstanding'))->toBe('150.00');

    $this->withToken($this->token)->withHeader('Idempotency-Key', 'c1')
        ->postJson($this->base.'/dues/'.$id.'/collect', ['method' => 'cash', 'amount' => 150])
        ->assertOk()
        ->assertJsonPath('data.balance', '0.00');

    expect($this->withToken($this->token)->getJson($this->base.'/dues')->json('data.dues'))->toHaveCount(0);
});

it('refuses to collect more than is owed', function (): void {
    $id = createApiOrder($this);
    $this->withToken($this->token)->withHeader('Idempotency-Key', 'd1')
        ->postJson($this->base.'/orders/'.$id.'/due', ['lines' => [['method' => 'cash', 'amount' => 50]]])->assertOk();

    $this->withToken($this->token)->withHeader('Idempotency-Key', 'c1')
        ->postJson($this->base.'/dues/'.$id.'/collect', ['method' => 'cash', 'amount' => 500])
        ->assertUnprocessable();
});

it('hides dues from a role without order access', function (): void {
    $kitchen = User::factory()->role('rider', $this->branch->id)->create();
    $token = $kitchen->createToken('staff', ['staff'])->plainTextToken;

    $this->withToken($token)->getJson($this->base.'/dues')->assertForbidden();
});

it('deletes an order in one step with a reason', function (): void {
    $id = createApiOrder($this);

    $this->withToken($this->token)
        ->postJson($this->base.'/orders/'.$id.'/delete', ['reason_code' => 'wrong_item'])
        ->assertOk()
        ->assertJsonPath('data.status', 'void');

    expect(Order::query()->find($id)->isVoid())->toBeTrue();
});

it('rejects an unknown delete reason', function (): void {
    $id = createApiOrder($this);

    $this->withToken($this->token)
        ->postJson($this->base.'/orders/'.$id.'/delete', ['reason_code' => 'nope'])
        ->assertUnprocessable();
});

it('filters the order list by search and due', function (): void {
    $id = createApiOrder($this);
    Customer::query()->where('mobile', '01712345678')->exists();

    expect($this->withToken($this->token)->getJson($this->base.'/orders?filter[search]=01712345678')->json('data.orders'))->toHaveCount(1)
        ->and($this->withToken($this->token)->getJson($this->base.'/orders?filter[search]=99999')->json('data.orders'))->toHaveCount(0)
        ->and($this->withToken($this->token)->getJson($this->base.'/orders?filter[due]=1')->json('data.orders'))->toHaveCount(0);

    expect($id)->toBeInt();
});

it('takes, lists and closes a pre-order', function (): void {
    $created = $this->withToken($this->token)->withHeader('Idempotency-Key', 'p1')
        ->postJson($this->base.'/preorders', [
            'items' => [['menu_item_id' => $this->item->id, 'quantity' => 2]],
            'customer_name' => 'Rahim',
            'advance_amount' => 100,
        ])->assertCreated()
        ->assertJsonPath('data.total', '400.00')
        ->assertJsonPath('data.balance_due', '300.00');

    $id = $created->json('data.id');

    expect($this->withToken($this->token)->getJson($this->base.'/preorders')->json('data.preorders'))->toHaveCount(1);

    $this->withToken($this->token)->postJson($this->base.'/preorders/'.$id.'/complete')
        ->assertOk()->assertJsonPath('data.status', 'completed');

    $this->withToken($this->token)->postJson($this->base.'/preorders/'.$id.'/cancel')
        ->assertUnprocessable();

    expect(Preorder::query()->count())->toBe(1);
});

it('rejects an advance larger than the total', function (): void {
    $this->withToken($this->token)->withHeader('Idempotency-Key', 'p2')
        ->postJson($this->base.'/preorders', [
            'items' => [['menu_item_id' => $this->item->id, 'quantity' => 1]],
            'advance_amount' => 999,
        ])->assertUnprocessable();
});

it('adds dues and waiting pre-orders to the dashboard and payment methods to the reference', function (): void {
    $data = $this->withToken($this->token)->getJson('/api/v1/dashboard')->assertOk()->json('data');
    expect($data)->toHaveKeys(['dues', 'preorders_waiting']);

    $reference = $this->withToken($this->token)->getJson($this->base.'/reference')->assertOk()->json('data');
    expect($reference)->toHaveKeys(['payment_methods', 'void_reasons', 'order_edit_enabled', 'order_delete_enabled', 'pos_lock']);
});

it('reports website orders that arrived after a given order for the incoming alert', function (): void {
    $this->withToken($this->token)->getJson($this->base.'/orders/incoming')->assertOk()->assertJsonPath('data.orders', []);

    $id = createApiOrder($this);

    // A POS order is not "incoming": only website, QR and platform orders are.
    $this->withToken($this->token)->getJson($this->base.'/orders/incoming?after=0')->assertOk()->assertJsonPath('data.last_id', 0);

    $created = Order::query()->find($id);
    $created->update(['channel' => OrderChannel::Website->value]);

    $first = $this->withToken($this->token)->getJson($this->base.'/orders/incoming?after=0')->assertOk()->json('data');
    expect($first['last_id'])->toBe($id)->and($first['orders'])->toBe([]);
});
