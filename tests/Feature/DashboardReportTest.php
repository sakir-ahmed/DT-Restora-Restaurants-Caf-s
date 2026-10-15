<?php

declare(strict_types=1);

use App\Models\Branch;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\Ingredient;
use App\Models\IngredientStock;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Unit;
use App\Models\User;
use App\Models\Wastage;
use App\Services\Metrics\MetricsService;
use Database\Seeders\RoleSeeder;

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);

    $this->branch = Branch::factory()->create(['code' => 'BR01', 'service_charge_percent' => '0.00', 'vat_percent' => '5.00']);
    $this->owner = User::factory()->role('owner')->create();

    $category = MenuCategory::factory()->create(['branch_id' => $this->branch->id]);
    $this->item = MenuItem::factory()->create([
        'branch_id' => $this->branch->id,
        'menu_category_id' => $category->id,
        'base_price' => '200.00',
    ]);

    $this->cashier = User::factory()->role('cashier', $this->branch->id)->create();
});

function seedOrder($test, string $total, ?string $createdAt = null, string $status = 'paid', string $channel = 'pos'): int
{
    $createdAt ??= now()->toDateTimeString();

    $orderId = DB::table('orders')->insertGetId([
        'branch_id' => $test->branch->id,
        'invoice_no' => 'TEST-'.uniqid(),
        'order_type' => 'takeaway',
        'channel' => $channel,
        'status' => $status,
        'subtotal' => $total,
        'total' => $total,
        'vat_amount' => '0.00',
        'food_cost' => number_format((float) $total * 0.3, 4, '.', ''),
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
        'paid_at' => $status === 'paid' ? $createdAt : null,
    ]);

    DB::table('order_items')->insert([
        'order_id' => $orderId,
        'menu_item_id' => $test->item->id,
        'name' => 'Test Item',
        'unit_price' => $total,
        'quantity' => 1,
        'line_total' => $total,
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
    ]);

    return $orderId;
}

it('shows the dashboard with matching metrics to the owner', function (): void {
    seedOrder($this, '500.00');
    seedOrder($this, '300.00');
    seedOrder($this, '999.99', null, 'void'); // excluded

    $response = $this->actingAs($this->owner, 'staff')->get('/admin?branch='.$this->branch->id);

    $response->assertOk();

    $summary = $response->viewData('page')['props']['summary'];

    expect($summary['gross_sales'])->toBe('800.00')
        ->and($summary['net_sales'])->toBe('800.00')
        ->and($summary['orders'])->toBe(2);
});

it('computes the net profit from all cost lines', function (): void {
    seedOrder($this, '1000.00'); // food cost 300

    $unit = Unit::query()->create(['name' => 'Gram', 'symbol' => 'g-'.uniqid(), 'type' => 'weight', 'factor' => 1]);
    $ingredient = Ingredient::query()->create(['name' => 'Butter', 'unit_id' => $unit->id]);

    Employee::query()->create([
        'branch_id' => $this->branch->id,
        'name' => 'Chef One',
        'designation' => 'Chef',
        'monthly_salary' => '30000.00',
        'is_active' => true,
    ]);

    Expense::query()->create([
        'branch_id' => $this->branch->id,
        'expense_date' => now()->toDateString(),
        'category' => 'utilities',
        'amount' => '500.00',
    ]);

    Wastage::query()->create([
        'branch_id' => $this->branch->id,
        'ingredient_id' => $ingredient->id,
        'unit_id' => $unit->id,
        'qty' => '1',
        'reason_code' => 'spoiled',
        'cost' => '150.00',
    ]);

    $summary = app(MetricsService::class)->summary($this->branch->id, now()->startOfDay(), now()->endOfDay());

    // net 1000 - food 300 - wastage 150 - salary 1000 (30k/day 30) - expenses 500 = -950
    expect($summary['food_cost'])->toBe('300.00')
        ->and($summary['wastage_cost'])->toBe('150.00')
        ->and($summary['salary_cost'])->toBe('1000.00')
        ->and($summary['expenses'])->toBe('500.00')
        ->and($summary['net_profit'])->toBe('-950.00');
});

it('keeps dashboard and sales report totals identical', function (): void {
    seedOrder($this, '400.00');
    seedOrder($this, '250.00');

    $metrics = app(MetricsService::class);

    $dashboard = $metrics->summary($this->branch->id, now()->startOfDay(), now()->endOfDay());
    $reportRows = $metrics->salesByDayDetailed($this->branch->id, now()->startOfDay(), now()->endOfDay());

    $reportGross = number_format(array_sum(array_map(fn (array $row) => (float) $row['gross'], $reportRows)), 2, '.', '');

    expect($reportGross)->toBe($dashboard['gross_sales']);
});

it('renders the reports page and exports in all formats', function (): void {
    seedOrder($this, '150.00');

    $this->actingAs($this->owner, 'staff')->get('/admin/reports?type=sales')->assertOk();
    $this->actingAs($this->owner, 'staff')->get('/admin/reports?type=wastage')->assertOk();
    $this->actingAs($this->owner, 'staff')->get('/admin/reports?type=stock-valuation')->assertOk();
    $this->actingAs($this->owner, 'staff')->get('/admin/reports?type=food-cost')->assertOk();
    $this->actingAs($this->owner, 'staff')->get('/admin/reports?type=branch-pl')->assertOk();
    $this->actingAs($this->owner, 'staff')->get('/admin/reports?type=channel')->assertOk();
    $this->actingAs($this->owner, 'staff')->get('/admin/reports?type=payment-method')->assertOk();
    $this->actingAs($this->owner, 'staff')->get('/admin/reports?type=cashier')->assertOk();
    $this->actingAs($this->owner, 'staff')->get('/admin/reports?type=discount-void')->assertOk();
    $this->actingAs($this->owner, 'staff')->get('/admin/reports?type=delivery')->assertOk();
    $this->actingAs($this->owner, 'staff')->get('/admin/reports?type=loyalty')->assertOk();

    $pdf = $this->actingAs($this->owner, 'staff')->get('/admin/reports/export?type=sales&format=pdf');
    $pdf->assertOk();

    $xlsx = $this->actingAs($this->owner, 'staff')->get('/admin/reports/export?type=sales&format=xlsx');
    $xlsx->assertOk();

    $csv = $this->actingAs($this->owner, 'staff')->get('/admin/reports/export?type=sales&format=csv');
    $csv->assertOk();
});

it('records expenses with branch isolation and permissions', function (): void {
    $branchB = Branch::factory()->create(['code' => 'BR02']);
    $manager = User::factory()->role('branch-manager', $this->branch->id)->create();

    $response = $this->actingAs($manager, 'staff')->post('/admin/expenses', [
        'expense_date' => now()->toDateString(),
        'category' => 'utilities',
        'description' => 'Electricity bill',
        'amount' => '2400.50',
    ]);

    $response->assertRedirect();

    expect(Expense::count())->toBe(1)
        ->and(Expense::first()->branch_id)->toBe($this->branch->id);

    // Manager of BR01 cannot touch BR02 expenses.
    $expenseB = Expense::query()->create([
        'branch_id' => $branchB->id,
        'expense_date' => now()->toDateString(),
        'category' => 'rent',
        'amount' => '100.00',
    ]);

    $this->actingAs($manager, 'staff')->delete("/admin/expenses/{$expenseB->id}")->assertNotFound();

    // Cashier has no permission.
    $this->actingAs($this->cashier, 'staff')->post('/admin/expenses', [
        'expense_date' => now()->toDateString(),
        'category' => 'other',
        'amount' => '10.00',
    ])->assertForbidden();
});

it('raises low stock and wastage spike alerts', function (): void {
    $gram = Unit::query()->create(['name' => 'Gram', 'symbol' => 'g-'.uniqid(), 'type' => 'weight', 'factor' => 1]);
    $ingredient = Ingredient::query()->create(['name' => 'Milk', 'unit_id' => $gram->id, 'reorder_level' => 100]);

    IngredientStock::query()->create([
        'branch_id' => $this->branch->id,
        'ingredient_id' => $ingredient->id,
        'stock_qty' => 40,
        'avg_cost' => 1,
    ]);

    $metrics = app(MetricsService::class);

    $low = $metrics->lowStock($this->branch->id);

    expect($low)->toHaveCount(1)
        ->and($low[0]['name'])->toBe('Milk')
        ->and($low[0]['stock_qty'])->toBe('40');

    // Wastage spike: nothing last week, plenty today.
    Wastage::query()->create([
        'branch_id' => $this->branch->id,
        'ingredient_id' => $ingredient->id,
        'unit_id' => $gram->id,
        'qty' => '10',
        'reason_code' => 'spoiled',
        'cost' => '500.00',
    ]);

    expect($metrics->wastageSpike($this->branch->id))->toBeNull(); // no week baseline

    // With a baseline, today well above average triggers the alert.
    Wastage::query()->whereDate('created_at', today()->subDay())->delete();
    Wastage::query()->create([
        'branch_id' => $this->branch->id,
        'ingredient_id' => $ingredient->id,
        'unit_id' => $gram->id,
        'qty' => '1',
        'reason_code' => 'spoiled',
        'cost' => '50.00',
    ]);
    // Move it into the baseline window directly.
    Wastage::query()->latest('id')->first()->forceFill(['created_at' => now()->subDays(3)])->save();

    $spike = $metrics->wastageSpike($this->branch->id);

    expect($spike)->not->toBeNull()
        ->and($spike['today'])->toBe('500.00');

    $notifications = $this->actingAs($this->owner, 'staff')
        ->get('/admin')
        ->viewData('page')['props']['notifications'];

    expect(collect($notifications)->pluck('id')->all())
        ->toContain('low-stock')
        ->toContain('wastage-spike');
});

it('scopes the dashboard for a branch manager', function (): void {
    $manager = User::factory()->role('branch-manager', $this->branch->id)->create();

    $response = $this->actingAs($manager, 'staff')->get('/admin');

    $response->assertOk();

    $props = $response->viewData('page')['props'];

    expect($props['branchComparison'])->toBeEmpty();
});
