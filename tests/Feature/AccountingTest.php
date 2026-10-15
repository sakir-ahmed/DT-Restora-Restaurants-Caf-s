<?php

declare(strict_types=1);

use App\Actions\Accounting\PostJournalEntryAction;
use App\Actions\Inventory\LogWastageAction;
use App\Actions\Inventory\ReceivePurchaseAction;
use App\Actions\Sales\ApproveOrderVoidAction;
use App\Actions\Sales\CreateOrderAction;
use App\Actions\Sales\PayOrderAction;
use App\Actions\Sales\RequestOrderVoidAction;
use App\Enums\OrderType;
use App\Models\Account;
use App\Models\BankStatementLine;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\FiscalYear;
use App\Models\Ingredient;
use App\Models\IngredientStock;
use App\Models\Journal;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Purchase;
use App\Models\Recipe;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\Voucher;
use App\Services\Accounting\AccountingService;
use App\Services\Accounting\ReportService;
use App\Services\Metrics\MetricsService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UnitSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->seed([RoleSeeder::class, UnitSeeder::class, ChartOfAccountsSeeder::class]);

    $this->branch = Branch::factory()->create(['code' => 'BR01', 'vat_percent' => '0.00', 'service_charge_percent' => '0.00']);
    $this->cashier = User::factory()->role('cashier', $this->branch->id)->create();
    $this->manager = User::factory()->role('branch-manager', $this->branch->id)->create();
    $this->accountant = User::factory()->role('accountant')->create();

    $category = MenuCategory::factory()->create(['branch_id' => $this->branch->id]);
    $this->item = MenuItem::factory()->create([
        'branch_id' => $this->branch->id,
        'menu_category_id' => $category->id,
        'base_price' => '500.00',
    ]);

    $gram = Unit::query()->where('symbol', 'g')->firstOrFail();
    $this->ingredient = Ingredient::factory()->create(['unit_id' => $gram->id]);

    IngredientStock::query()->create([
        'branch_id' => $this->branch->id,
        'ingredient_id' => $this->ingredient->id,
        'stock_qty' => 10000,
        'avg_cost' => 0.1,
    ]);

    $recipe = Recipe::query()->create(['menu_item_id' => $this->item->id]);
    $recipe->items()->create(['ingredient_id' => $this->ingredient->id, 'qty' => 100]);

    $this->customer = Customer::query()->create(['name' => 'Ayesha', 'mobile' => '8801711000099']);
});

it('rejects an unbalanced journal entry', function (): void {
    expect(fn () => app(PostJournalEntryAction::class)->execute(
        entryDate: '2026-01-15',
        lines: [
            ['account_code' => '1100', 'debit' => 100],
            ['account_code' => '4000', 'credit' => 90],
        ],
    ))->toThrow(ValidationException::class);
});

it('posts a balanced entry and shows it in the trial balance', function (): void {
    app(PostJournalEntryAction::class)->execute(
        entryDate: '2026-01-15',
        lines: [
            ['account_code' => '1100', 'debit' => 500],
            ['account_code' => '4000', 'credit' => 500],
        ],
        note: 'Opening cash sale',
    );

    $tb = app(ReportService::class)->trialBalance('2026-01-01', '2026-01-31');

    expect($tb['balanced'])->toBeTrue()
        ->and($tb['debits'])->toBe($tb['credits'])
        ->and(count($tb['rows']))->toBe(2);
});

it('automatically posts a balanced entry when an order is paid', function (): void {
    $order = app(CreateOrderAction::class)->execute(
        branch: $this->branch,
        type: OrderType::Takeaway,
        lines: [['menu_item_id' => $this->item->id, 'quantity' => 2]],
        cashier: $this->cashier,
    );

    app(PayOrderAction::class)->executeCash($order, 1000);

    $journal = Journal::query()->where('source_id', $order->id)->where('source_type', $order->getMorphClass())->firstOrFail();

    expect($journal->totalDebit())->toBe($journal->totalCredit())
        ->and($journal->totalDebit())->toBe(1020.0) // cash 1000 + cogs 20
        ->and($journal->status)->toBe(Journal::STATUS_POSTED);

    // Double payment attempts never double-post: exactly one journal.
    expect(Journal::query()->where('source_id', $order->id)->count())->toBe(1);
});

it('reverses the journal when an order is voided', function (): void {
    $order = app(CreateOrderAction::class)->execute(
        branch: $this->branch,
        type: OrderType::Takeaway,
        lines: [['menu_item_id' => $this->item->id, 'quantity' => 1]],
        cashier: $this->cashier,
    );

    app(PayOrderAction::class)->executeCash($order, 500);

    $request = app(RequestOrderVoidAction::class)->execute($order, $this->cashier, 'wrong_item');
    app(ApproveOrderVoidAction::class)->execute($request, $this->manager);

    $original = Journal::query()->where('source_id', $order->id)->where('status', '!=', Journal::STATUS_REVERSED)->where('reverses_journal_id', '!=', null)->first();

    expect(Journal::query()->where('source_id', $order->id)->where('status', Journal::STATUS_REVERSED)->exists())->toBeTrue();

    $tb = app(ReportService::class)->trialBalance('2000-01-01', '2100-01-01');
    expect($tb['balanced'])->toBeTrue();
});

it('posts purchase receipts and wastage', function (): void {
    $purchase = Purchase::query()->create([
        'branch_id' => $this->branch->id,
        'supplier_id' => Supplier::factory()->create()->id,
        'purchased_at' => now()->toDateString(),
        'status' => Purchase::STATUS_DRAFT,
    ]);

    $purchase->items()->create([
        'ingredient_id' => $this->ingredient->id,
        'unit_id' => Unit::query()->where('symbol', 'kg')->firstOrFail()->id,
        'qty' => 5,
        'unit_cost' => 100,
        'line_total' => 500,
    ]);

    app(ReceivePurchaseAction::class)->execute($purchase);

    $purchaseJournal = Journal::query()->where('source_id', $purchase->id)->where('source_type', $purchase->getMorphClass())->firstOrFail();

    expect($purchaseJournal->totalDebit())->toBe(500.0);

    app(LogWastageAction::class)->execute(
        branch: $this->branch,
        ingredient: $this->ingredient,
        unit: Unit::query()->where('symbol', 'g')->firstOrFail(),
        qty: 100,
        reasonCode: 'spoiled',
        note: null,
    );

    $wastageJournal = Journal::query()->where('source_type', 'App\\Models\\Wastage')->firstOrFail();

    expect($wastageJournal->totalDebit())->toBe(10.0); // 100 g x 0.1
});

it('locks fiscal periods and rejects postings into them', function (): void {
    $year = FiscalYear::query()->first();
    $year->update(['is_locked' => true]);

    expect(fn () => app(PostJournalEntryAction::class)->execute(
        entryDate: now()->toDateString(),
        lines: [
            ['account_code' => '1100', 'debit' => 100],
            ['account_code' => '4000', 'credit' => 100],
        ],
    ))->toThrow(ValidationException::class);
});

it('guards the accounting screens by permission', function (): void {
    $this->actingAs($this->accountant, 'staff')->get('/admin/accounting')->assertOk();
    $this->actingAs($this->cashier, 'staff')->get('/admin/accounting')->assertForbidden();
});

it('exports the trial balance and profit and loss as PDF', function (): void {
    app(PostJournalEntryAction::class)->execute(
        entryDate: now()->toDateString(),
        lines: [
            ['account_code' => '1100', 'debit' => 900],
            ['account_code' => '4000', 'credit' => 900],
        ],
    );

    $this->actingAs($this->accountant, 'staff')->get('/admin/accounting/export/trial-balance')->assertOk();
    $this->actingAs($this->accountant, 'staff')->get('/admin/accounting/export/profit-loss')->assertOk();
    $this->actingAs($this->accountant, 'staff')->get('/admin/accounting/export/balance-sheet')->assertOk();
});

it('posts daily salary once and matches the owner dashboard profit', function (): void {
    Employee::query()->create([
        'branch_id' => $this->branch->id,
        'name' => 'Chef One',
        'designation' => 'Chef',
        'monthly_salary' => '30000.00',
        'is_active' => true,
    ]);

    $order = app(CreateOrderAction::class)->execute(
        branch: $this->branch,
        type: OrderType::Takeaway,
        lines: [['menu_item_id' => $this->item->id, 'quantity' => 1]],
        cashier: $this->cashier,
    );

    app(PayOrderAction::class)->executeCash($order, 500);

    $accounting = app(AccountingService::class);
    $accounting->postDailySalary(now());
    $accounting->postDailySalary(now());

    expect(Journal::query()->where('reference', 'like', 'SAL-%')->count())->toBe(1);

    $from = now()->startOfDay();
    $to = now()->endOfDay();
    $dashboard = app(MetricsService::class)->summary(null, $from, $to);
    $profit = app(ReportService::class)->profitAndLoss($from->toDateString(), $to->toDateString());

    expect($profit['net_profit'])->toBe($dashboard['net_profit']);
});

it('keeps the balance sheet closed with retained earnings', function (): void {
    app(PostJournalEntryAction::class)->execute(
        entryDate: '2026-02-01',
        lines: [
            ['account_code' => '1100', 'debit' => 500],
            ['account_code' => '4000', 'credit' => 500],
        ],
    );

    $sheet = app(ReportService::class)->balanceSheet('2026-02-28');

    expect($sheet['total_assets'])->toBe('500.00')
        ->and($sheet['total_liabilities_equity'])->toBe('500.00');
});

it('shows a ledger, cash flow, aged payables, vouchers and a matched statement', function (): void {
    Storage::fake('local');

    app(PostJournalEntryAction::class)->execute(
        entryDate: now()->toDateString(),
        lines: [
            ['account_code' => '1100', 'debit' => 500],
            ['account_code' => '4000', 'credit' => 500],
        ],
        reference: 'CASH-1',
    );

    $ledger = app(ReportService::class)->ledger('1100', now()->startOfMonth()->toDateString(), now()->toDateString());
    $flow = app(ReportService::class)->cashFlow(now()->startOfMonth()->toDateString(), now()->toDateString());

    expect($ledger[0]['balance'])->toBe('500.00')
        ->and($flow['inflow'])->toBe('500.00')
        ->and($flow['net'])->toBe('500.00');

    $supplier = Supplier::factory()->create();

    Purchase::query()->create([
        'branch_id' => $this->branch->id,
        'supplier_id' => $supplier->id,
        'purchased_at' => now()->subDays(10)->toDateString(),
        'total' => '1000.00',
        'status' => Purchase::STATUS_RECEIVED,
    ]);

    $this->actingAs($this->accountant, 'staff')->post('/admin/accounting/vouchers', [
        'type' => 'payment',
        'amount' => '400.00',
        'entry_date' => now()->toDateString(),
        'pay_from_code' => '1100',
        'supplier_id' => $supplier->id,
        'attachment' => UploadedFile::fake()->create('bill.pdf', 20, 'application/pdf'),
    ])->assertRedirect();

    $aging = app(ReportService::class)->payablesAging();

    expect($aging)->toHaveCount(1)
        ->and($aging[0]['total'])->toBe('600.00')
        ->and($aging[0]['current'])->toBe('600.00')
        ->and(Voucher::query()->first()->journal_id)->not->toBeNull()
        ->and(Storage::disk('local')->allFiles('vouchers'))->not->toBeEmpty();

    $cash = Account::query()->where('code', '1100')->firstOrFail();

    $this->actingAs($this->accountant, 'staff')->put('/admin/accounting/accounts/'.$cash->id, [
        'name' => 'Cash drawer',
        'is_active' => true,
        'code' => '9999',
    ])->assertRedirect();

    expect($cash->fresh()->code)->toBe('1100')
        ->and($cash->fresh()->name)->toBe('Cash drawer');

    $this->actingAs($this->accountant, 'staff')->put('/admin/accounting/accounts/'.$cash->id, [
        'name' => 'Cash drawer',
        'is_active' => false,
    ])->assertSessionHasErrors('is_active');

    $journal = Journal::query()->where('reference', 'CASH-1')->firstOrFail();

    $this->actingAs($this->accountant, 'staff')->post('/admin/accounting/statements', [
        'account_code' => '1100',
        'occurred_on' => now()->toDateString(),
        'amount' => '500.00',
        'description' => 'Morning deposit',
    ])->assertRedirect();

    $line = BankStatementLine::query()->firstOrFail();

    $this->actingAs($this->accountant, 'staff')->post('/admin/accounting/statements/'.$line->id.'/match', [
        'journal_id' => $journal->id,
    ])->assertRedirect();

    expect($line->fresh()->journal_id)->toBe($journal->id);

    $this->actingAs($this->cashier, 'staff')->post('/admin/accounting/vouchers', [
        'type' => 'expense',
        'amount' => '10.00',
        'entry_date' => now()->toDateString(),
        'pay_from_code' => '1100',
    ])->assertForbidden();
});
