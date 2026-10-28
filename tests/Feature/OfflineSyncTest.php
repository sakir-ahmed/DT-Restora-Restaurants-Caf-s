<?php

declare(strict_types=1);

use App\Models\Branch;
use App\Models\DataVersion;
use App\Models\Ingredient;
use App\Models\IngredientStock;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Recipe;
use App\Models\RestaurantTable;
use App\Models\Shift;
use App\Models\StockCount;
use App\Models\SyncItem;
use App\Models\Unit;
use App\Models\User;
use App\Models\Wastage;
use Database\Seeders\ChartOfAccountsSeeder as ChartOfAccountsSeederAlias;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UnitSeeder;

beforeEach(function (): void {
    $this->seed([ChartOfAccountsSeederAlias::class, RoleSeeder::class, UnitSeeder::class]);

    $this->branch = Branch::factory()->create(['code' => 'BR01', 'vat_percent' => '0.00', 'service_charge_percent' => '0.00']);
    $this->cashier = User::factory()->role('cashier', $this->branch->id)->create();

    $category = MenuCategory::factory()->create(['branch_id' => $this->branch->id]);
    $this->item = MenuItem::factory()->create([
        'branch_id' => $this->branch->id,
        'menu_category_id' => $category->id,
        'base_price' => '200.00',
    ]);
});

function pushItem($test, array $overrides = []): array
{
    $token = $test->cashier->createToken('pos', ['staff'])->plainTextToken;

    $item = array_merge([
        'client_id' => '01H-'.uniqid(),
        'type' => 'order',
        'payload' => [
            'branch_id' => $test->branch->id,
            'order_type' => 'takeaway',
            'terminal' => 'T02',
            'tendered' => 200,
            'items' => [['menu_item_id' => $test->item->id, 'quantity' => 1]],
        ],
        'created_at_device' => now()->subMinutes(2)->toIso8601String(),
        'data_version' => 1,
    ], $overrides);

    return [$test->withToken($token)->postJson('/api/v1/sync/push', ['items' => [$item]]), $item['client_id']];
}

it('accepts an offline order, syncs idempotently and pays it', function (): void {
    [$response, $clientId] = pushItem($this);

    $response->assertOk()
        ->assertJsonPath('data.results.0.status', SyncItem::STATUS_ACCEPTED);

    expect(Order::count())->toBe(1)
        ->and(Order::first()->status->value)->toBe('paid')
        ->and(Order::first()->invoice_no)->toStartWith('BR01-T02-'.now()->format('Ymd').'-');

    // Retrying the same client_id never creates a duplicate.
    $retry = $this->withToken($this->cashier->createToken('pos', ['staff'])->plainTextToken)
        ->postJson('/api/v1/sync/push', ['items' => [[
            'client_id' => $clientId,
            'type' => 'order',
            'payload' => ['branch_id' => $this->branch->id, 'order_type' => 'takeaway', 'items' => [['menu_item_id' => $this->item->id, 'quantity' => 1]]],
        ]]]);

    $retry->assertOk()
        ->assertJsonPath('data.results.0.status', SyncItem::STATUS_DUPLICATE);

    expect(Order::count())->toBe(1);
});

it('one failing item never blocks the others', function (): void {
    $token = $this->cashier->createToken('pos', ['staff'])->plainTextToken;

    $response = $this->withToken($token)->postJson('/api/v1/sync/push', ['items' => [
        [
            'client_id' => '01H-BAD',
            'type' => 'wastage',
            'payload' => ['nonsense' => true],
        ],
        [
            'client_id' => '01H-GOOD',
            'type' => 'order',
            'payload' => [
                'branch_id' => $this->branch->id,
                'order_type' => 'takeaway',
                'items' => [['menu_item_id' => $this->item->id, 'quantity' => 1]],
            ],
        ],
    ]]);

    $response->assertOk();

    $results = collect($response->json('data.results'))->keyBy('id');

    expect($results['01H-BAD']['status'])->toBe(SyncItem::STATUS_CONFLICT)
        ->and($results['01H-GOOD']['status'])->toBe(SyncItem::STATUS_ACCEPTED)
        ->and(Order::count())->toBe(1);
});

it('records conflicts for the manager screen with reason codes', function (): void {
    // Stock exactly one serving, then push two orders.
    $gram = Unit::query()->where('symbol', 'g')->firstOrFail();
    $ingredient = Ingredient::factory()->create(['unit_id' => $gram->id]);

    IngredientStock::query()->create([
        'branch_id' => $this->branch->id,
        'ingredient_id' => $ingredient->id,
        'stock_qty' => 0, // nothing left
        'avg_cost' => 0.1,
    ]);

    $recipe = Recipe::query()->create(['menu_item_id' => $this->item->id]);
    $recipe->items()->create(['ingredient_id' => $ingredient->id, 'qty' => 100]);

    [$response] = pushItem($this);

    $response->assertOk();

    expect(SyncItem::query()->where('status', SyncItem::STATUS_CONFLICT)->count())->toBe(1)
        ->and(SyncItem::query()->where('status', SyncItem::STATUS_CONFLICT)->first()->conflict_reason)->toBeTruthy();

    // Manager sees the conflict screen.
    $manager = User::factory()->role('branch-manager', $this->branch->id)->create();

    $this->actingAs($manager, 'staff')->get('/admin/sync-conflicts')->assertOk();
});

it('serves the bootstrap and delta endpoints', function (): void {
    $token = $this->cashier->createToken('pos', ['staff'])->plainTextToken;

    $bootstrap = $this->withToken($token)->getJson('/api/v1/sync/bootstrap');
    $bootstrap->assertOk()
        ->assertJsonPath('data.branch.code', 'BR01');

    $version = $bootstrap->json('data.data_version');

    $same = $this->withToken($token)->getJson("/api/v1/sync/changes?since={$version}");
    $same->assertOk()
        ->assertJsonPath('data.changed', false);

    DataVersion::bump();

    $changed = $this->withToken($token)->getJson("/api/v1/sync/changes?since={$version}");
    $changed->assertOk()
        ->assertJsonPath('data.changed', true);
});

it('keeps the invoice series per offline terminal without collisions', function (): void {
    [$first] = pushItem($this, ['client_id' => '01H-T1']);
    [$second] = pushItem($this, ['client_id' => '01H-T2']);

    $invoices = Order::query()->pluck('invoice_no');

    expect($invoices)->toHaveCount(2)
        ->and($invoices->unique()->count())->toBe(2);
});

it('rejects a sync push that has neither a staff session nor a token', function (): void {
    $this->postJson('/api/v1/sync/push', [
        'items' => [[
            'client_id' => '01H-GUEST',
            'type' => 'order',
            'payload' => ['branch_id' => $this->branch->id],
        ]],
    ])->assertUnauthorized();
});

it('accepts a sync push from the logged-in staff session', function (): void {
    $this->post('/login', [
        'login' => $this->cashier->username,
        'password' => 'password',
    ])->assertRedirect();

    $this->withHeader('Origin', 'http://localhost')
        ->postJson('/api/v1/sync/push', [
            'items' => [[
                'client_id' => '01H-SESSION',
                'type' => 'order',
                'payload' => [
                    'branch_id' => $this->branch->id,
                    'order_type' => 'takeaway',
                    'terminal' => 'T02',
                    'tendered' => 200,
                    'items' => [['menu_item_id' => $this->item->id, 'quantity' => 1]],
                ],
            ]],
        ])
        ->assertOk()
        ->assertJsonPath('data.results.0.status', SyncItem::STATUS_ACCEPTED);
});

it('syncs a shift open and close once each', function (): void {
    $token = $this->cashier->createToken('pos', ['staff'])->plainTextToken;

    $open = $this->withToken($token)->postJson('/api/v1/sync/push', ['items' => [[
        'client_id' => '01H-SHIFT-OPEN',
        'type' => 'shift',
        'payload' => [
            'action' => 'open',
            'branch_id' => $this->branch->id,
            'opening_cash' => '500.00',
            'terminal' => 'T02',
        ],
    ]]]);

    $open->assertOk()->assertJsonPath('data.results.0.status', SyncItem::STATUS_ACCEPTED);

    $this->withToken($token)->postJson('/api/v1/sync/push', ['items' => [[
        'client_id' => '01H-SHIFT-OPEN',
        'type' => 'shift',
        'payload' => ['action' => 'open', 'branch_id' => $this->branch->id, 'opening_cash' => '500.00'],
    ]]])->assertJsonPath('data.results.0.status', SyncItem::STATUS_DUPLICATE);

    expect(Shift::query()->count())->toBe(1);

    $this->withToken($token)->postJson('/api/v1/sync/push', ['items' => [[
        'client_id' => '01H-SHIFT-CLOSE',
        'type' => 'shift',
        'payload' => [
            'action' => 'close',
            'branch_id' => $this->branch->id,
            'counted_cash' => '500.00',
        ],
    ]]])->assertJsonPath('data.results.0.status', SyncItem::STATUS_ACCEPTED);

    expect(Shift::query()->first()->closed_at)->not->toBeNull()
        ->and((string) Shift::query()->first()->difference)->toBe('0.00');
});

it('syncs wastage and a stock count for a kitchen manager', function (): void {
    $kitchen = User::factory()->role('kitchen-manager', $this->branch->id)->create();
    $token = $kitchen->createToken('pos', ['staff'])->plainTextToken;
    $gram = Unit::query()->where('symbol', 'g')->firstOrFail();
    $ingredient = Ingredient::factory()->create(['unit_id' => $gram->id]);

    IngredientStock::query()->create([
        'branch_id' => $this->branch->id,
        'ingredient_id' => $ingredient->id,
        'stock_qty' => 1000,
        'avg_cost' => 0.1,
    ]);

    $this->withToken($token)->postJson('/api/v1/sync/push', ['items' => [[
        'client_id' => '01H-WASTE',
        'type' => 'wastage',
        'payload' => [
            'branch_id' => $this->branch->id,
            'ingredient_id' => $ingredient->id,
            'unit_id' => $gram->id,
            'qty' => 100,
            'reason_code' => 'spoiled',
        ],
    ]]])->assertJsonPath('data.results.0.status', SyncItem::STATUS_ACCEPTED);

    expect(Wastage::query()->count())->toBe(1)
        ->and((float) IngredientStock::query()->where('ingredient_id', $ingredient->id)->value('stock_qty'))->toBe(900.0);

    $this->withToken($token)->postJson('/api/v1/sync/push', ['items' => [[
        'client_id' => '01H-COUNT',
        'type' => 'stock_count',
        'payload' => [
            'action' => 'close',
            'branch_id' => $this->branch->id,
            'items' => [['ingredient_id' => $ingredient->id, 'counted_qty' => 800]],
        ],
    ]]])->assertJsonPath('data.results.0.status', SyncItem::STATUS_ACCEPTED);

    expect(StockCount::query()->first()->status)->toBe(StockCount::STATUS_CLOSED)
        ->and((float) IngredientStock::query()->where('ingredient_id', $ingredient->id)->value('stock_qty'))->toBe(800.0);

    expect($this->cashier->can('wastage.log'))->toBeFalse();

    $this->app['auth']->forgetGuards();

    $this->withToken($this->cashier->createToken('pos', ['staff'])->plainTextToken)
        ->postJson('/api/v1/sync/push', ['items' => [[
            'client_id' => '01H-WASTE-CASHIER',
            'type' => 'wastage',
            'payload' => [
                'branch_id' => $this->branch->id,
                'ingredient_id' => $ingredient->id,
                'unit_id' => $gram->id,
                'qty' => 10,
                'reason_code' => 'spoiled',
            ],
        ]]])->assertJsonPath('data.results.0.status', SyncItem::STATUS_CONFLICT);

    expect(Wastage::query()->count())->toBe(1);
});

it('rejects attendance sync items because clock-in is not supported', function (): void {
    $token = $this->cashier->createToken('pos', ['staff'])->plainTextToken;

    $this->withToken($token)->postJson('/api/v1/sync/push', [
        'items' => [[
            'client_id' => '01H-ATT',
            'type' => 'attendance',
            'payload' => ['note' => 'clock in'],
        ]],
    ])->assertUnprocessable()
        ->assertJsonPath('success', false)
        ->assertJsonPath('error.code', 'VALIDATION_FAILED')
        ->assertJsonStructure(['error' => ['details' => ['items.0.type']]]);
});

it('keeps the invoice number printed offline and continues that terminal series for counter patterns', function (): void {
    $this->branch->update(['pos_settings' => ['invoice_pattern' => '{BRANCH}-{TERMINAL}-{YYYY}{MM}{DD}-{SEQ}']]);

    $day = now()->format('Ymd');
    $invoice = "BR01-T02-{$day}-0007";

    [$accepted] = pushItem($this, [
        'client_id' => '01H-INV7',
        'payload' => [
            'branch_id' => $this->branch->id,
            'order_type' => 'takeaway',
            'terminal' => 'T02',
            'invoice_no' => $invoice,
            'tendered' => 200,
            'items' => [['menu_item_id' => $this->item->id, 'quantity' => 1]],
        ],
    ]);

    $accepted->assertOk()
        ->assertJsonPath('data.results.0.status', SyncItem::STATUS_ACCEPTED)
        ->assertJsonPath('data.results.0.invoice_no', $invoice)
        ->assertJsonPath('data.results.0.invoice_changed', false);

    expect(Order::query()->where('client_id', '01H-INV7')->value('invoice_no'))->toBe($invoice)
        ->and(Order::query()->where('client_id', '01H-INV7')->first()->status->value)->toBe('paid');

    [$next] = pushItem($this, ['client_id' => '01H-INV8']);

    $next->assertOk()
        ->assertJsonPath('data.results.0.status', SyncItem::STATUS_ACCEPTED);

    expect(Order::query()->where('client_id', '01H-INV8')->value('invoice_no'))->toBe("BR01-T02-{$day}-0008");
});

it('accepts offline random invoice numbers from parallel terminals and keeps each one', function (): void {
    $day = now()->format('Ymd');
    $invoices = ["BR01-T01-{$day}-143205-K7Q4", "BR01-T02-{$day}-143205-9ZXM", "BR01-T03-{$day}-143205-K7Q4"];

    foreach ($invoices as $index => $invoice) {
        [$response] = pushItem($this, [
            'client_id' => "01H-RND{$index}",
            'payload' => [
                'branch_id' => $this->branch->id,
                'order_type' => 'takeaway',
                'terminal' => 'T0'.($index + 1),
                'invoice_no' => $invoice,
                'items' => [['menu_item_id' => $this->item->id, 'quantity' => 1]],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.results.0.status', SyncItem::STATUS_ACCEPTED)
            ->assertJsonPath('data.results.0.invoice_no', $invoice)
            ->assertJsonPath('data.results.0.invoice_changed', false);
    }

    expect(Order::query()->orderBy('id')->pluck('invoice_no')->all())->toBe($invoices);
});

it('issues a new invoice number instead of losing a sale when an offline number is already used', function (): void {
    $invoice = 'BR01-T02-'.now()->format('Ymd').'-143205-K7Q4';
    $payload = [
        'branch_id' => $this->branch->id,
        'order_type' => 'takeaway',
        'terminal' => 'T02',
        'invoice_no' => $invoice,
        'items' => [['menu_item_id' => $this->item->id, 'quantity' => 1]],
    ];

    [$first] = pushItem($this, ['client_id' => '01H-INV-A', 'payload' => $payload]);
    $first->assertJsonPath('data.results.0.status', SyncItem::STATUS_ACCEPTED);

    [$second] = pushItem($this, ['client_id' => '01H-INV-B', 'payload' => $payload]);

    $second->assertOk()
        ->assertJsonPath('data.results.0.status', SyncItem::STATUS_ACCEPTED)
        ->assertJsonPath('data.results.0.invoice_changed', true);

    $reissued = Order::query()->where('client_id', '01H-INV-B')->value('invoice_no');

    expect($reissued)->not->toBe($invoice)
        ->and($second->json('data.results.0.invoice_no'))->toBe($reissued)
        ->and(Order::query()->count())->toBe(2)
        ->and(Order::query()->where('invoice_no', $invoice)->count())->toBe(1);

    // A retry of the same sale reports the number it ended up with, and makes no new order.
    [$retry] = pushItem($this, ['client_id' => '01H-INV-B', 'payload' => $payload]);

    $retry->assertJsonPath('data.results.0.status', SyncItem::STATUS_DUPLICATE)
        ->assertJsonPath('data.results.0.invoice_no', $reissued)
        ->assertJsonPath('data.results.0.invoice_changed', true);

    expect(Order::query()->count())->toBe(2);
});

it('issues a fresh number when the offline number does not follow the branch pattern', function (): void {
    [$response] = pushItem($this, [
        'client_id' => '01H-INV-ODD',
        'payload' => [
            'branch_id' => $this->branch->id,
            'order_type' => 'takeaway',
            'terminal' => 'T02',
            'invoice_no' => 'MADE-UP-1',
            'items' => [['menu_item_id' => $this->item->id, 'quantity' => 1]],
        ],
    ]);

    $response->assertJsonPath('data.results.0.status', SyncItem::STATUS_ACCEPTED)
        ->assertJsonPath('data.results.0.invoice_changed', true);

    expect(Order::query()->value('invoice_no'))->toMatch('/^BR01-T02-\d{8}-\d{6}-[0-9A-HJKMNP-TV-Z]{4}$/');
});

it('pays an already synced offline order from the device client id', function (): void {
    $token = $this->cashier->createToken('pos', ['staff'])->plainTextToken;

    $this->withToken($token)->postJson('/api/v1/sync/push', ['items' => [[
        'client_id' => '01H-OPEN-ORDER',
        'type' => 'order',
        'payload' => [
            'branch_id' => $this->branch->id,
            'order_type' => 'takeaway',
            'terminal' => 'T02',
            'items' => [['menu_item_id' => $this->item->id, 'quantity' => 1]],
        ],
        'created_at_device' => now()->subMinutes(2)->toIso8601String(),
    ]]])->assertJsonPath('data.results.0.status', SyncItem::STATUS_ACCEPTED);

    expect(Order::query()->where('client_id', '01H-OPEN-ORDER')->first()->status->value)->toBe('pending');

    $this->withToken($token)->postJson('/api/v1/sync/push', ['items' => [[
        'client_id' => '01H-OPEN-PAY',
        'type' => 'payment',
        'payload' => [
            'order_client_id' => '01H-OPEN-ORDER',
            'method' => 'cash',
            'tendered' => 200,
        ],
        'created_at_device' => now()->subMinute()->toIso8601String(),
    ]]])->assertOk()
        ->assertJsonPath('data.results.0.status', SyncItem::STATUS_ACCEPTED);

    expect(Order::query()->count())->toBe(1)
        ->and(Order::query()->first()->status->value)->toBe('paid');
});

it('keeps the table, customer, discount and note of an offline order', function (): void {
    $table = RestaurantTable::factory()->create(['branch_id' => $this->branch->id]);

    [$response] = pushItem($this, [
        'payload' => [
            'branch_id' => $this->branch->id,
            'order_type' => 'dine_in',
            'terminal' => 'T02',
            'restaurant_table_id' => $table->id,
            'customer_mobile' => '01712345678',
            'discount' => ['type' => 'fixed', 'value' => 20],
            'note' => 'No onion',
            'items' => [['menu_item_id' => $this->item->id, 'quantity' => 1]],
        ],
    ]);

    $response->assertOk()->assertJsonPath('data.results.0.status', SyncItem::STATUS_ACCEPTED);

    $order = Order::query()->first();

    expect($order->restaurant_table_id)->toBe($table->id)
        ->and($order->note)->toBe('No onion')
        ->and((float) $order->discount_amount)->toBe(20.0)
        ->and($order->customer?->mobile)->toBe(normalize_bd_mobile('01712345678'));
});

it('revises a synced order and moves its status from queued offline edits', function (): void {
    pushItem($this, ['client_id' => '01H-EDIT-ORDER', 'payload' => [
        'branch_id' => $this->branch->id,
        'order_type' => 'takeaway',
        'terminal' => 'T02',
        'items' => [['menu_item_id' => $this->item->id, 'quantity' => 1]],
    ]]);

    $token = $this->cashier->createToken('pos', ['staff'])->plainTextToken;

    $this->withToken($token)->postJson('/api/v1/sync/push', ['items' => [[
        'client_id' => '01H-EDIT-1',
        'type' => 'order_edit',
        'payload' => [
            'order_client_id' => '01H-EDIT-ORDER',
            'order_type' => 'takeaway',
            'note' => 'Extra spicy',
            'items' => [['menu_item_id' => $this->item->id, 'quantity' => 3]],
        ],
    ]]])->assertOk()->assertJsonPath('data.results.0.status', SyncItem::STATUS_ACCEPTED);

    $order = Order::query()->where('client_id', '01H-EDIT-ORDER')->first();

    expect($order->items()->sum('quantity'))->toBe(3)
        ->and($order->note)->toBe('Extra spicy');

    $this->withToken($token)->postJson('/api/v1/sync/push', ['items' => [[
        'client_id' => '01H-EDIT-2',
        'type' => 'order_status',
        'payload' => ['order_client_id' => '01H-EDIT-ORDER', 'status' => 'preparing'],
    ]]])->assertOk()->assertJsonPath('data.results.0.status', SyncItem::STATUS_ACCEPTED);

    expect($order->refresh()->status->value)->toBe('preparing');
});

it('reports a conflict for an invalid queued status change', function (): void {
    pushItem($this, ['client_id' => '01H-BAD-STATUS', 'payload' => [
        'branch_id' => $this->branch->id,
        'order_type' => 'takeaway',
        'terminal' => 'T02',
        'items' => [['menu_item_id' => $this->item->id, 'quantity' => 1]],
    ]]);

    $this->withToken($this->cashier->createToken('pos', ['staff'])->plainTextToken)
        ->postJson('/api/v1/sync/push', ['items' => [[
            'client_id' => '01H-BAD-STATUS-1',
            'type' => 'order_status',
            'payload' => ['order_client_id' => '01H-BAD-STATUS', 'status' => 'nonsense'],
        ]]])->assertOk()->assertJsonPath('data.results.0.status', SyncItem::STATUS_CONFLICT);
});
