<?php

declare(strict_types=1);

use App\Models\Branch;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemVariant;
use App\Models\Order;
use App\Models\User;
use App\Services\CostingService;
use Database\Seeders\ProductionData\ArmansKitchen\ArmansKitchenFreshStartSeeder;
use Database\Seeders\ProductionData\ArmansKitchen\ArmansKitchenMenuSeeder;
use Database\Seeders\ProductionData\ArmansKitchen\ArmansKitchenResetSeeder;
use Database\Seeders\UnitSeeder;
use Illuminate\Support\Facades\DB;

/**
 * Menu item => [selling price, cost stated by the owner].
 *
 * @return array<string, array{0: int, 1: float}>
 */
function statedCosts(): array
{
    return [
        'French Fry' => [100, 13.07],
        'Chicken Fry (1 Pc)' => [120, 79.55],
        'Chicken Meatbox' => [200, 56.57],
        'Crispy Wings' => [260, 54.85],
        'BBQ Wings (6 Pcs)' => [280, 127.7],
        'Cashewnut Salad' => [280, 49.3],
        'Chicken Strip with Rice' => [300, 65.77],
        'Chicken Strip with Fries' => [300, 54.5],
        'Fish Fry Thai' => [300, 108.62],
        'Fried Rice with Chicken Fry' => [180, 120.1],
        'Fried Rice with Chili Chicken' => [200, 74.9],
        'Fried Rice with BBQ Wings (2 Pcs)' => [220, 78.67],
        'Fried Rice with BBQ Chicken' => [220, 77.7],
        'Chicken Chowmein' => [230, 63.35],
        'Chicken Sub Sandwich' => [150, 55.72],
        'Chicken Sharma' => [150, 39.35],
        'Regular Chotpoti' => [70, 18.75],
        'Shahi Chotpoti' => [100, 41.0],
        'Regular Fuchka' => [80, 19.55],
        'Churmur' => [100, 19.55],
        'Doi Fuchka' => [140, 27.15],
        'Student Burger' => [70, 26.07],
        'Regular Burger' => [120, 38.3],
        'Naga Burger' => [130, 40.3],
        'Cheesy Burger' => [170, 50.3],
        'Jumbo Burger' => [250, 56.8],
        'Cheesy Jumbo Burger' => [300, 80.8],
        'Rong Cha' => [10, 1.6],
        'Masala Rong Cha' => [20, 3.3],
        'Dudh Cha' => [30, 5.2],
        'Pora Ruti' => [30, 7.95],
        'Regular Coffee' => [40, 13.7],
        'Horlicks Dudh Cha' => [50, 15.7],
        'Lassi' => [100, 20.8],
        'Cold Coffee' => [100, 18.05],
        'Chocolate Milk Shake' => [100, 32.2],
    ];
}

it('sets up only the costed menu on a fresh database', function (): void {
    $this->seed(ArmansKitchenResetSeeder::class);

    expect(Branch::query()->count())->toBe(1)
        ->and(MenuItem::query()->withoutGlobalScopes()->count())->toBe(36)
        ->and(User::query()->count())->toBe(0)
        ->and(Order::query()->count())->toBe(0)
        ->and(MenuItemVariant::query()->count())->toBe(0);
});

it('replaces the existing menu and keeps users', function (): void {
    $user = User::factory()->create();
    $this->seed(UnitSeeder::class);
    $branch = Branch::factory()->create(['code' => 'BR01']);
    $category = MenuCategory::factory()->create(['branch_id' => $branch->id]);
    $old = MenuItem::factory()->create(['branch_id' => $branch->id, 'menu_category_id' => $category->id]);
    MenuItemVariant::query()->create(['menu_item_id' => $old->id, 'name' => 'Large', 'price' => 10]);

    $this->seed(ArmansKitchenResetSeeder::class);

    expect(MenuItem::query()->withoutGlobalScopes()->count())->toBe(36)
        ->and(MenuItem::query()->withoutGlobalScopes()->whereKey($old->id)->exists())->toBeFalse()
        ->and(MenuItemVariant::query()->count())->toBe(0)
        ->and($user->fresh())->not->toBeNull();
});

it('prices every item and costs its recipe close to the owner figures', function (): void {
    $this->seed(ArmansKitchenResetSeeder::class);

    $branch = Branch::query()->firstOrFail();

    foreach (statedCosts() as $name => [$price, $stated]) {
        $item = MenuItem::query()->withoutGlobalScopes()->with('variants')->where('name', $name)->firstOrFail();
        $costing = app(CostingService::class)->recipeCost($item, $branch->id);

        expect((float) $item->base_price)->toBe((float) $price, $name);
        expect(abs((float) $costing['cost'] - $stated))->toBeLessThanOrEqual(0.75, "{$name}: {$costing['cost']} vs {$stated}");
    }
});

it('can be run again without duplicating anything', function (): void {
    $this->seed(ArmansKitchenResetSeeder::class);
    $this->seed(ArmansKitchenMenuSeeder::class);

    expect(MenuItem::query()->withoutGlobalScopes()->count())->toBe(36)
        ->and(Branch::query()->count())->toBe(1);
});

it('stops the reset when orders use the current menu', function (): void {
    $this->seed(UnitSeeder::class);
    $branch = Branch::factory()->create(['code' => 'BR01']);
    $category = MenuCategory::factory()->create(['branch_id' => $branch->id]);
    $old = MenuItem::factory()->create(['branch_id' => $branch->id, 'menu_category_id' => $category->id]);
    $orderId = DB::table('orders')->insertGetId([
        'branch_id' => $branch->id, 'invoice_no' => 'INV-1', 'order_type' => 'takeaway', 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('order_items')->insert([
        'order_id' => $orderId, 'menu_item_id' => $old->id, 'name' => 'Old', 'unit_price' => 10,
        'quantity' => 1, 'line_total' => 10, 'created_at' => now(), 'updated_at' => now(),
    ]);

    expect(fn () => $this->seed(ArmansKitchenResetSeeder::class))->toThrow(RuntimeException::class);

    expect(Order::query()->count())->toBe(1);
});

it('deletes old orders and their books on a fresh start, then loads the menu', function (): void {
    $this->seed(UnitSeeder::class);
    $branch = Branch::factory()->create(['code' => 'BR01']);
    $category = MenuCategory::factory()->create(['branch_id' => $branch->id]);
    $old = MenuItem::factory()->create(['branch_id' => $branch->id, 'menu_category_id' => $category->id]);
    $orderId = DB::table('orders')->insertGetId([
        'branch_id' => $branch->id, 'invoice_no' => 'INV-1', 'order_type' => 'takeaway', 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('order_items')->insert([
        'order_id' => $orderId, 'menu_item_id' => $old->id, 'name' => 'Old', 'unit_price' => 10,
        'quantity' => 1, 'line_total' => 10, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('payments')->insert(['order_id' => $orderId, 'method' => 'cash', 'amount' => 10, 'created_at' => now(), 'updated_at' => now()]);
    DB::table('journals')->insert([
        'entry_date' => now()->toDateString(), 'source_type' => (new Order)->getMorphClass(), 'source_id' => $orderId,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $user = User::factory()->create();

    $this->seed(ArmansKitchenFreshStartSeeder::class);

    expect(Order::query()->count())->toBe(0)
        ->and(DB::table('order_items')->count())->toBe(0)
        ->and(DB::table('payments')->count())->toBe(0)
        ->and(DB::table('journals')->count())->toBe(0)
        ->and(MenuItem::query()->withoutGlobalScopes()->count())->toBe(36)
        ->and($user->fresh())->not->toBeNull();
});
