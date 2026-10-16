<?php

declare(strict_types=1);

use App\Models\Branch;
use App\Models\Employee;
use App\Models\Journal;
use App\Models\StaffAttendance;
use App\Models\User;
use App\Services\Accounting\AccountingService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function (): void {
    $this->travelTo('2026-10-16 15:00:00');
    $this->seed([RoleSeeder::class, ChartOfAccountsSeeder::class]);

    $this->branch = Branch::factory()->create(['code' => 'BR01']);
    $this->accountant = User::factory()->role('accountant')->create();
    $this->manager = User::factory()->role('branch-manager', $this->branch->id)->create();
});

it('calculates daily hourly and monthly pay and subtracts an advance', function (): void {
    $daily = Employee::factory()->create([
        'branch_id' => $this->branch->id,
        'name' => 'Daily Cook',
        'pay_basis' => 'daily',
        'pay_rate' => '800.00',
        'monthly_salary' => '0.00',
    ]);
    $hourly = Employee::factory()->create([
        'branch_id' => $this->branch->id,
        'name' => 'Hourly Helper',
        'pay_basis' => 'hourly',
        'pay_rate' => '100.00',
        'monthly_salary' => '0.00',
    ]);
    $monthly = Employee::factory()->create([
        'branch_id' => $this->branch->id,
        'name' => 'Monthly Chef',
        'pay_basis' => 'monthly',
        'pay_rate' => '30000.00',
        'monthly_salary' => '30000.00',
    ]);

    foreach (['2026-10-14', '2026-10-15'] as $day) {
        StaffAttendance::query()->create([
            'branch_id' => $this->branch->id,
            'employee_id' => $daily->id,
            'work_date' => $day,
            'status' => 'present',
            'clock_in' => $day.' 09:00:00',
            'clock_out' => $day.' 17:00:00',
            'source' => 'manual',
        ]);
    }

    StaffAttendance::query()->create([
        'branch_id' => $this->branch->id,
        'employee_id' => $hourly->id,
        'work_date' => '2026-10-16',
        'status' => 'present',
        'clock_in' => '2026-10-16 09:00:00',
        'clock_out' => '2026-10-16 13:00:00',
        'source' => 'manual',
    ]);

    foreach (['2026-10-14', '2026-10-15', '2026-10-16'] as $day) {
        StaffAttendance::query()->create([
            'branch_id' => $this->branch->id,
            'employee_id' => $monthly->id,
            'work_date' => $day,
            'status' => 'present',
            'clock_in' => $day.' 09:00:00',
            'clock_out' => $day.' 17:00:00',
            'source' => 'manual',
        ]);
    }

    $advance = $this->actingAs($this->accountant, 'staff')->post('/admin/payroll/advances', [
        'employee_id' => $hourly->id,
        'amount' => '150.00',
        'advanced_on' => '2026-10-16',
        'note' => 'Family emergency',
    ]);

    $advance->assertRedirect();

    $journal = Journal::query()->where('reference', 'like', 'ADV-%')->with('lines.account')->firstOrFail();
    $debit = $journal->lines->first(fn ($line) => (float) $line->debit > 0);
    $credit = $journal->lines->first(fn ($line) => (float) $line->credit > 0);

    expect($debit->account->code)->toBe('2300')
        ->and($credit->account->code)->toBe('1100')
        ->and($debit->debit)->toBe('150.00')
        ->and($credit->credit)->toBe('150.00')
        ->and($journal->totalDebit())->toBe($journal->totalCredit());

    $page = $this->actingAs($this->accountant, 'staff')->get('/admin/payroll?from=2026-10-01&to=2026-10-16');
    $rows = collect($page->viewData('page')['props']['rows'])->keyBy('name');

    expect($rows['Daily Cook']['gross'])->toBe('1600.00')
        ->and($rows['Daily Cook']['net'])->toBe('1600.00')
        ->and($rows['Hourly Helper']['hours'])->toBe('4.00')
        ->and($rows['Hourly Helper']['gross'])->toBe('400.00')
        ->and($rows['Hourly Helper']['advances'])->toBe('150.00')
        ->and($rows['Hourly Helper']['net'])->toBe('250.00')
        ->and($rows['Monthly Chef']['days'])->toBe(3)
        ->and($rows['Monthly Chef']['gross'])->toBe('3000.00')
        ->and($rows['Monthly Chef']['net'])->toBe('3000.00');
});

it('keeps the daily salary journal on the monthly allocation and adds attendance pay once', function (): void {
    Employee::factory()->create([
        'branch_id' => $this->branch->id,
        'pay_basis' => 'monthly',
        'monthly_salary' => '30000.00',
        'pay_rate' => '30000.00',
        'is_active' => true,
    ]);

    $daily = Employee::factory()->create([
        'branch_id' => $this->branch->id,
        'pay_basis' => 'daily',
        'pay_rate' => '500.00',
        'monthly_salary' => '0.00',
        'is_active' => true,
    ]);

    StaffAttendance::query()->create([
        'branch_id' => $this->branch->id,
        'employee_id' => $daily->id,
        'work_date' => '2026-10-16',
        'status' => 'present',
        'clock_in' => '2026-10-16 09:00:00',
        'clock_out' => '2026-10-16 17:00:00',
        'source' => 'manual',
    ]);

    $accounting = app(AccountingService::class);
    $accounting->postDailySalary(now());
    $accounting->postDailySalary(now());

    $journal = Journal::query()->where('reference', 'SAL-BR01-20261016')->firstOrFail();

    expect(Journal::query()->where('reference', 'like', 'SAL-%')->count())->toBe(1)
        ->and(number_format($journal->totalDebit(), 2, '.', ''))->toBe('1500.00');
});

it('forbids a cashier and a branch manager from payroll', function (): void {
    $cashier = User::factory()->role('cashier', $this->branch->id)->create();

    $this->actingAs($cashier, 'staff')->get('/admin/payroll')->assertForbidden();
    $this->actingAs($this->manager, 'staff')->post('/admin/payroll/advances', [
        'employee_id' => Employee::factory()->create(['branch_id' => $this->branch->id])->id,
        'amount' => '10.00',
        'advanced_on' => '2026-10-16',
    ])->assertForbidden();

    expect(Journal::query()->count())->toBe(0);
});
