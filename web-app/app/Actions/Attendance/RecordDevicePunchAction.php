<?php

declare(strict_types=1);

namespace App\Actions\Attendance;

use App\Models\AttendanceDevice;
use App\Models\AttendancePunch;
use App\Models\Employee;
use App\Models\Scopes\BranchScope;
use App\Models\StaffAttendance;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Map one ZKTeco (or bridge) punch onto the same staff attendance row
 * used by manual entry.
 *
 * Authentication is the device token, sent as
 * `Authorization: Bearer {token}`. The plain token is shown once when
 * the device is registered.
 *
 * JSON body:
 * - device_user_id (string, required): the id enrolled on the device.
 *   It must match employees.device_user_id in the device's branch.
 * - punched_at (string, required): ISO-8601 datetime of the punch.
 * - direction (string, optional): "in" or "out". When omitted, the first
 *   punch of the day is clock-in and a later punch is clock-out.
 *
 * Idempotent on device + device_user_id + punched_at (to the second).
 * A repeated post returns the original attendance and does not move
 * the clocks. An unknown device user is rejected without storing a
 * punch, so the same payload can be retried after the employee is linked.
 */
class RecordDevicePunchAction
{
    public function execute(AttendanceDevice $device, string $deviceUserId, CarbonInterface $punchedAt, ?string $direction = null): StaffAttendance
    {
        $moment = CarbonImmutable::parse($punchedAt)
            ->setTimezone((string) config('app.timezone'))
            ->startOfSecond();

        if ($moment->greaterThan(now()->addMinutes(5))) {
            throw ValidationException::withMessages([
                'punched_at' => __('attendance.future_time'),
            ]);
        }

        $deviceUserId = trim($deviceUserId);
        $direction = in_array($direction, ['in', 'out'], true) ? $direction : null;

        return DB::transaction(function () use ($device, $deviceUserId, $moment, $direction): StaffAttendance {
            $existing = AttendancePunch::query()
                ->where('attendance_device_id', $device->id)
                ->where('device_user_id', $deviceUserId)
                ->where('punched_at', $moment->format('Y-m-d H:i:s'))
                ->first();

            if ($existing?->staff_attendance_id !== null) {
                $device->update(['last_seen_at' => now()]);

                return $existing->attendance()->firstOrFail();
            }

            $employee = Employee::query()
                ->withoutGlobalScope(BranchScope::class)
                ->where('branch_id', $device->branch_id)
                ->where('device_user_id', $deviceUserId)
                ->first();

            if ($employee === null) {
                throw ValidationException::withMessages([
                    'device_user_id' => __('attendance.unknown_device_user'),
                ]);
            }

            if (! $employee->is_active) {
                throw ValidationException::withMessages([
                    'device_user_id' => __('attendance.inactive_employee'),
                ]);
            }

            $attendance = $this->applyPunch($device, $employee, $moment, $direction);

            try {
                AttendancePunch::query()->create([
                    'attendance_device_id' => $device->id,
                    'device_user_id' => $deviceUserId,
                    'punched_at' => $moment->format('Y-m-d H:i:s'),
                    'direction' => $direction,
                    'employee_id' => $employee->id,
                    'staff_attendance_id' => $attendance->id,
                ]);
            } catch (UniqueConstraintViolationException) {
                $punch = AttendancePunch::query()
                    ->where('attendance_device_id', $device->id)
                    ->where('device_user_id', $deviceUserId)
                    ->where('punched_at', $moment->format('Y-m-d H:i:s'))
                    ->firstOrFail();

                $device->update(['last_seen_at' => now()]);

                return $punch->attendance()->firstOrFail();
            }

            $device->update(['last_seen_at' => now()]);

            return $attendance->refresh();
        });
    }

    private function applyPunch(AttendanceDevice $device, Employee $employee, CarbonImmutable $moment, ?string $direction): StaffAttendance
    {
        $attendance = StaffAttendance::query()
            ->withoutGlobalScope(BranchScope::class)
            ->where('employee_id', $employee->id)
            ->whereDate('work_date', $moment->toDateString())
            ->lockForUpdate()
            ->first();

        $treatAsOut = $direction === 'out'
            || ($direction === null && $attendance?->clock_in !== null && $attendance->clock_out === null);

        if ($attendance === null) {
            return StaffAttendance::query()->create([
                'branch_id' => $device->branch_id,
                'employee_id' => $employee->id,
                'work_date' => $moment->toDateString(),
                'status' => StaffAttendance::STATUS_PRESENT,
                'clock_in' => $treatAsOut ? null : $moment->toMutable(),
                'clock_out' => $treatAsOut ? $moment->toMutable() : null,
                'source' => StaffAttendance::SOURCE_DEVICE,
                'attendance_device_id' => $device->id,
            ]);
        }

        $attendance->status = StaffAttendance::STATUS_PRESENT;
        $attendance->source = StaffAttendance::SOURCE_DEVICE;
        $attendance->attendance_device_id = $device->id;

        if ($treatAsOut) {
            if ($attendance->clock_in === null || $moment->greaterThan($attendance->clock_in)) {
                if ($attendance->clock_out === null || $moment->greaterThan($attendance->clock_out)) {
                    $attendance->clock_out = $moment->toMutable();
                }
            }
        } elseif ($attendance->clock_in === null || $moment->lessThan($attendance->clock_in)) {
            $attendance->clock_in = $moment->toMutable();
        }

        $attendance->save();

        return $attendance;
    }
}
