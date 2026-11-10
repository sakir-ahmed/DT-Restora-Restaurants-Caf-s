<?php

declare(strict_types=1);

namespace App\Actions\Attendance;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\StaffAttendance;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordStaffAttendanceAction
{
    /**
     * @param  array{work_date: string, status: string, clock_in?: string|null, clock_out?: string|null}  $data
     */
    public function execute(Branch $branch, Employee $employee, array $data, ?User $user = null): StaffAttendance
    {
        if ($employee->branch_id !== $branch->id) {
            throw ValidationException::withMessages([
                'employee_id' => __('attendance.wrong_branch'),
            ]);
        }

        $workDate = CarbonImmutable::parse($data['work_date'])->startOfDay();

        if ($workDate->greaterThan(now()->startOfDay())) {
            throw ValidationException::withMessages([
                'work_date' => __('attendance.future_date'),
            ]);
        }

        $status = $data['status'];
        $clockIn = null;
        $clockOut = null;

        if ($status === StaffAttendance::STATUS_PRESENT) {
            if (empty($data['clock_in'])) {
                throw ValidationException::withMessages([
                    'clock_in' => __('attendance.clock_in_required'),
                ]);
            }

            $clockIn = CarbonImmutable::parse($data['clock_in']);
            $clockOut = empty($data['clock_out']) ? null : CarbonImmutable::parse($data['clock_out']);

            if ($clockIn->greaterThan(now())) {
                throw ValidationException::withMessages([
                    'clock_in' => __('attendance.future_time'),
                ]);
            }

            if ($clockOut !== null && $clockOut->greaterThan(now())) {
                throw ValidationException::withMessages([
                    'clock_out' => __('attendance.future_time'),
                ]);
            }

            if ($clockOut !== null && $clockOut->lessThanOrEqualTo($clockIn)) {
                throw ValidationException::withMessages([
                    'clock_out' => __('attendance.clock_out_after_in'),
                ]);
            }
        }

        return DB::transaction(function () use ($branch, $employee, $workDate, $status, $clockIn, $clockOut, $user): StaffAttendance {
            $existing = StaffAttendance::query()
                ->where('employee_id', $employee->id)
                ->whereDate('work_date', $workDate->toDateString())
                ->lockForUpdate()
                ->first();

            $this->assertNoOverlap($employee, $clockIn, $clockOut, $existing?->id);

            $payload = [
                'branch_id' => $branch->id,
                'employee_id' => $employee->id,
                'work_date' => $workDate->toDateString(),
                'status' => $status,
                'clock_in' => $clockIn,
                'clock_out' => $clockOut,
                'source' => StaffAttendance::SOURCE_MANUAL,
                'attendance_device_id' => null,
                'recorded_by' => $user?->id,
            ];

            if ($existing !== null) {
                $existing->update($payload);

                return $existing->refresh();
            }

            return StaffAttendance::query()->create($payload);
        });
    }

    private function assertNoOverlap(Employee $employee, ?CarbonInterface $clockIn, ?CarbonInterface $clockOut, ?int $ignoreId): void
    {
        if ($clockIn === null) {
            return;
        }

        $end = $clockOut ?? now();

        $conflict = StaffAttendance::query()
            ->where('employee_id', $employee->id)
            ->where('status', StaffAttendance::STATUS_PRESENT)
            ->whereNotNull('clock_in')
            ->when($ignoreId !== null, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->get()
            ->first(function (StaffAttendance $row) use ($clockIn, $end): bool {
                $rowEnd = $row->clock_out ?? now();

                return $row->clock_in->lessThan($end) && $rowEnd->greaterThan($clockIn);
            });

        if ($conflict !== null) {
            throw ValidationException::withMessages([
                'clock_in' => __('attendance.overlap'),
            ]);
        }
    }
}
