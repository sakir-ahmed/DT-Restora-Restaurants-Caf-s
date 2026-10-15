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
use App\Models\SyncItem;
use App\Models\Unit;
use App\Models\User;
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
        ->and(Order::first()->invoice_no)->toStartWith('BR01-T02-');

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
