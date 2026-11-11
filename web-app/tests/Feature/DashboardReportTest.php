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

it('shows expenses by category and due balances on the dashboard', function (): void {
    foreach ([['rent', '700.00'], ['utilities', '200.00'], ['rent', '100.00']] as [$category, $amount]) {
        Expense::query()->create([
            'branch_id' => $this->branch->id,
            'expense_date' => now()->toDateString(),
            'category' => $category,
            'amount' => $amount,
        ]);
    }

    $dueOrderId = seedOrder($this, '1000.00');
    DB::table('orders')->where('id', $dueOrderId)->update(['is_due' => true]);

    foreach ([['400.00', false], ['100.00', true]] as [$amount, $isCollection]) {
        DB::table('payments')->insert([
            'order_id' => $dueOrderId,
            'method' => 'cash',
            'amount' => $amount,
            'status' => 'completed',
            'is_due_collection' => $isCollection,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $response = $this->actingAs($this->owner, 'staff')->get('/admin?branch='.$this->branch->id);

    $response->assertOk();

    $props = $response->viewData('page')['props'];

    expect($props['expensesByCategory'])->toBe([
        ['category' => 'rent', 'total' => '800.00'],
        ['category' => 'utilities', 'total' => '200.00'],
    ])->and($props['dues']['outstanding'])->toBe('500.00')
        ->and($props['dues']['open_count'])->toBe(1)
        ->and($props['dues']['new_due'])->toBe('600.00')
        ->and($props['dues']['collected'])->toBe('100.00')
        ->and($props['dues']['customers'])->toBe(1)
        ->and($props['dues']['top'])->toHaveCount(1)
        ->and($props['dues']['top'][0]['balance'])->toBe(500.0);
});

it('passes the previous period dates and has explanations in every language', function (): void {
    $response = $this->actingAs($this->owner, 'staff')->get('/admin?period=7days&branch='.$this->branch->id);

    $props = $response->viewData('page')['props'];

    expect($props['previousFrom'])->toBe(now()->subDays(13)->toDateString())
        ->and($props['previousTo'])->toBe(now()->subDays(7)->toDateString());

    $keys = array_keys(trans('dashboard.info.items', [], 'en'));

    expect($keys)->toContain('expenses', 'net_profit', 'dues');

    foreach (['en', 'bn'] as $locale) {
        foreach ($keys as $key) {
            foreach (['title', 'what', 'formula'] as $part) {
                expect(trans("dashboard.info.items.$key.$part", [], $locale))
                    ->not->toBe("dashboard.info.items.$key.$part");
            }
        }
    }
});

it('compares the reorder level in the ingredient unit, not the base unit', function (): void {
    $gram = Unit::query()->create(['name' => 'Gram', 'symbol' => 'g-'.uniqid(), 'type' => 'weight', 'factor' => 1]);
    $kg = Unit::query()->create(['name' => 'Kilogram', 'symbol' => 'kg-'.uniqid(), 'type' => 'weight', 'base_unit_id' => $gram->id, 'factor' => 1000]);

    $low = Ingredient::query()->create(['name' => 'Beef', 'unit_id' => $kg->id, 'reorder_level' => 10, 'is_active' => true]);
    $enough = Ingredient::query()->create(['name' => 'Rice', 'unit_id' => $kg->id, 'reorder_level' => 10, 'is_active' => true]);
    $noStockRow = Ingredient::query()->create(['name' => 'Salt', 'unit_id' => $kg->id, 'reorder_level' => 2, 'is_active' => true]);
    $noLevel = Ingredient::query()->create(['name' => 'Water', 'unit_id' => $kg->id, 'reorder_level' => 0, 'is_active' => true]);

    IngredientStock::query()->create(['branch_id' => $this->branch->id, 'ingredient_id' => $low->id, 'stock_qty' => 8000, 'avg_cost' => 1]);
    IngredientStock::query()->create(['branch_id' => $this->branch->id, 'ingredient_id' => $enough->id, 'stock_qty' => 12000, 'avg_cost' => 1]);

    $names = collect(app(MetricsService::class)->lowStock($this->branch->id));

    expect($names->pluck('name')->all())->toBe(['Beef', 'Salt'])
        ->and($names->firstWhere('name', 'Beef')['stock_qty'])->toBe('8')
        ->and($names->firstWhere('name', 'Beef')['reorder_level'])->toBe('10')
        ->and($names->firstWhere('name', 'Salt')['stock_qty'])->toBe('0')
        ->and($noLevel->id)->not->toBeIn($names->pluck('id')->all());
});
