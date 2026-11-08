<?php

declare(strict_types=1);

namespace App\Actions\Payroll;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\SalaryAdvance;
use App\Models\User;
use App\Services\Accounting\AccountingService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordSalaryAdvanceAction
{
    public function __construct(private readonly AccountingService $accounting) {}

    /**
     * @param  array{amount: float|string, advanced_on: string, note?: string|null}  $data
     */
    public function execute(Branch $branch, Employee $employee, array $data, ?User $user = null): SalaryAdvance
    {
        if ($employee->branch_id !== $branch->id) {
            throw ValidationException::withMessages([
                'employee_id' => __('payroll.wrong_branch'),
            ]);
        }

        $advancedOn = CarbonImmutable::parse($data['advanced_on'])->startOfDay();

        if ($advancedOn->greaterThan(now()->startOfDay())) {
            throw ValidationException::withMessages([
                'advanced_on' => __('payroll.future_date'),
            ]);
        }

        $amount = round((float) $data['amount'], 2);

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => __('payroll.amount_required'),
            ]);
        }

        return DB::transaction(function () use ($branch, $employee, $advancedOn, $amount, $data, $user): SalaryAdvance {
            $advance = SalaryAdvance::query()->create([
                'branch_id' => $branch->id,
                'employee_id' => $employee->id,
                'amount' => number_format($amount, 2, '.', ''),
                'advanced_on' => $advancedOn->toDateString(),
                'note' => $data['note'] ?? null,
                'recorded_by' => $user?->id,
            ]);

            $journal = $this->accounting->postSalaryAdvance($advance, $user);
            $advance->update(['journal_id' => $journal->id]);

            return $advance->refresh();
        });
    }
}
