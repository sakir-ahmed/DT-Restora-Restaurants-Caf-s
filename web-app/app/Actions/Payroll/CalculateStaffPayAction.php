<?php

declare(strict_types=1);

namespace App\Actions\Payroll;

use App\Enums\PayBasis;
use App\Models\Employee;
use App\Models\SalaryAdvance;
use App\Models\Scopes\BranchScope;
use App\Models\StaffAttendance;
use Carbon\CarbonInterface;

class CalculateStaffPayAction
{
    /**
     * Period pay from attendance. Monthly staff earn (monthly rate / 30)
     * for each present day. Daily staff earn the daily rate per present
     * day. Hourly staff earn the hourly rate times clocked hours.
     * Advances recorded in the period are subtracted. The daily salary
     * journal still allocates a full calendar day of monthly salary; this
     * method is the payable figure for a salary slip.
     *
     * @return array{basis: string, rate: string, days: int, hours: string, gross: string, advances: string, net: string}
     */
    public function forPeriod(Employee $employee, CarbonInterface $from, CarbonInterface $to): array
    {
        $attendances = StaffAttendance::query()
            ->withoutGlobalScope(BranchScope::class)
            ->where('employee_id', $employee->id)
            ->where('status', StaffAttendance::STATUS_PRESENT)
            ->whereDate('work_date', '>=', $from->toDateString())
            ->whereDate('work_date', '<=', $to->toDateString())
            ->get();

        $days = $attendances->count();
        $hours = round($attendances->sum(fn (StaffAttendance $row): float => $row->hours()), 2);
        $basis = $employee->pay_basis ?? PayBasis::Monthly;
        $rate = (float) $employee->rateAmount();

        $gross = match ($basis) {
            PayBasis::Daily => round($days * $rate, 2),
            PayBasis::Hourly => round($hours * $rate, 2),
            PayBasis::Monthly => round(($rate / 30) * $days, 2),
        };

        $advances = round((float) SalaryAdvance::query()
            ->withoutGlobalScope(BranchScope::class)
            ->where('employee_id', $employee->id)
            ->whereDate('advanced_on', '>=', $from->toDateString())
            ->whereDate('advanced_on', '<=', $to->toDateString())
            ->sum('amount'), 2);

        return [
            'basis' => $basis->value,
            'rate' => number_format($rate, 2, '.', ''),
            'days' => $days,
            'hours' => number_format($hours, 2, '.', ''),
            'gross' => number_format($gross, 2, '.', ''),
            'advances' => number_format($advances, 2, '.', ''),
            'net' => number_format(round($gross - $advances, 2), 2, '.', ''),
        ];
    }

    /**
     * Daily and hourly cost already earned from attendance. Monthly staff
     * stay on the calendar allocation in the daily salary journal.
     */
    public function attendanceCost(int $branchId, CarbonInterface $from, CarbonInterface $to): float
    {
        $rows = StaffAttendance::query()
            ->withoutGlobalScope(BranchScope::class)
            ->where('branch_id', $branchId)
            ->where('status', StaffAttendance::STATUS_PRESENT)
            ->whereDate('work_date', '>=', $from->toDateString())
            ->whereDate('work_date', '<=', $to->toDateString())
            ->with(['employee' => fn ($query) => $query->withoutGlobalScope(BranchScope::class)])
            ->get();

        $total = 0.0;

        foreach ($rows as $row) {
            $employee = $row->employee;

            if ($employee === null) {
                continue;
            }

            $rate = (float) $employee->pay_rate;

            if ($employee->pay_basis === PayBasis::Daily) {
                $total += $rate;
            } elseif ($employee->pay_basis === PayBasis::Hourly) {
                $total += round($row->hours() * $rate, 2);
            }
        }

        return round($total, 2);
    }
}
