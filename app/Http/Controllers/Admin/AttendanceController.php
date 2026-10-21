<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Attendance\RecordStaffAttendanceAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreStaffAttendanceRequest;
use App\Models\AttendanceDevice;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\StaffAttendance;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('attendance.manage');

        $branch = $this->currentBranch($request);
        $date = $request->date('date') ?? $this->defaultDate($branch);
        $day = $date->toDateString();

        $employees = Employee::query()
            ->where('branch_id', $branch->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'designation', 'device_user_id', 'photo_path']);

        $attendances = StaffAttendance::query()
            ->where('branch_id', $branch->id)
            ->whereDate('work_date', $day)
            ->get()
            ->keyBy('employee_id');

        $rows = $employees
            ->map(function (Employee $employee) use ($attendances): array {
                $attendance = $attendances->get($employee->id);

                return [
                    'id' => $employee->id,
                    'employee_code' => $employee->device_user_id ?? 'EMP-'.str_pad((string) $employee->id, 4, '0', STR_PAD_LEFT),
                    'employee' => $employee->name,
                    'designation' => $employee->designation ?? '',
                    'photo_url' => $employee->photo_path ? '/storage/'.ltrim($employee->photo_path, '/') : null,
                    'status' => $attendance?->status ?? 'not_recorded',
                    'clock_in' => $attendance?->clock_in?->format('h:i A'),
                    'clock_out' => $attendance?->clock_out?->format('h:i A'),
                    'hours' => number_format($attendance?->hours() ?? 0.0, 2, '.', ''),
                    'source' => $attendance?->source,
                ];
            })
            ->sortBy('employee_code', SORT_NATURAL)
            ->values()
            ->all();

        $devices = AttendanceDevice::query()
            ->where('branch_id', $branch->id)
            ->orderBy('name')
            ->get(['id', 'name', 'serial', 'ip_address', 'is_active', 'last_seen_at']);

        return Inertia::render('admin/attendance/Index', [
            'date' => $day,
            'rows' => $rows,
            'employees' => $employees,
            'devices' => $devices,
        ]);
    }

    public function store(StoreStaffAttendanceRequest $request, RecordStaffAttendanceAction $action): RedirectResponse
    {
        $branch = $this->currentBranch($request);
        $employee = Employee::query()
            ->where('branch_id', $branch->id)
            ->find($request->integer('employee_id'));

        if ($employee === null) {
            abort(404);
        }

        $action->execute($branch, $employee, $request->validated(), $request->user('staff'));

        return back()->with('success', __('attendance.recorded'));
    }

    /**
     * Today, unless nothing has been recorded yet today (early morning):
     * then the latest day that has attendance, so the page is never empty.
     */
    private function defaultDate(Branch $branch): CarbonImmutable
    {
        $today = CarbonImmutable::today();

        $hasToday = StaffAttendance::query()
            ->where('branch_id', $branch->id)
            ->whereDate('work_date', $today->toDateString())
            ->exists();

        if ($hasToday) {
            return $today;
        }

        $latest = StaffAttendance::query()
            ->where('branch_id', $branch->id)
            ->where('work_date', '<=', $today->toDateString())
            ->max('work_date');

        return $latest !== null ? CarbonImmutable::parse((string) $latest) : $today;
    }

    private function currentBranch(Request $request): Branch
    {
        $user = $request->user('staff');

        $branch = Branch::query()->find($user->branch_id ?? (int) session('branch_id', 0));

        return $branch ?? Branch::query()->orderBy('id')->firstOrFail();
    }
}
