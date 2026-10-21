<?php

declare(strict_types=1);

use App\Enums\OrderChannel;
use App\Models\Branch;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\DemoAggregatorSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function (): void {
    $this->seed([ChartOfAccountsSeeder::class, RoleSeeder::class]);

    $this->branch = Branch::factory()->create(['code' => 'BR01']);
    $this->owner = User::factory()->role('owner')->create(['branch_id' => $this->branch->id]);

    $category = MenuCategory::factory()->create(['branch_id' => $this->branch->id]);
    MenuItem::factory()->count(6)->create([
        'branch_id' => $this->branch->id,
        'menu_category_id' => $category->id,
        'is_available' => true,
    ]);
});

it('seeds platform channels with live orders for the kitchen', function (): void {
    $this->seed(DemoAggregatorSeeder::class);

    expect(Order::query()->where('channel', OrderChannel::Aggregator->value)->count())->toBeGreaterThan(5);
    expect(Order::query()->whereHas('aggregatorChannel', fn ($q) => $q->where('key', 'foodpanda'))->exists())->toBeTrue();
});

it('lists only new website and platform orders after the given id', function (): void {
    $this->seed(DemoAggregatorSeeder::class);

    $start = $this->actingAs($this->owner, 'staff')->getJson(route('admin.orders.incoming'))->assertOk()->json();
    expect($start['orders'])->toBe([])->and($start['last_id'])->toBeGreaterThan(0);

    $order = Order::query()->where('channel', OrderChannel::Aggregator->value)->where('status', 'pending')->first();
    $after = $order->id;
    $next = Order::query()->where('channel', OrderChannel::Aggregator->value)->where('status', 'pending')->where('id', '>', $after)->exists();
    expect($next)->toBeTrue();

    $found = $this->actingAs($this->owner, 'staff')
        ->getJson(route('admin.orders.incoming', ['after' => $after]))
        ->assertOk()
        ->json('orders');

    expect($found)->not->toBeEmpty()
        ->and($found[0]['aggregator_channel']['key'])->not->toBeEmpty();
});
