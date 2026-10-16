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
        $date = $request->date('date') ?? CarbonImmutable::today();
        $day = $date->toDateString();

        $rows = StaffAttendance::query()
            ->where('branch_id', $branch->id)
            ->whereDate('work_date', $day)
            ->with('employee:id,name,designation')
            ->orderBy('employee_id')
            ->get()
            ->map(fn (StaffAttendance $row): array => [
                'id' => $row->id,
                'employee' => $row->employee->name ?? '',
                'designation' => $row->employee->designation ?? '',
                'status' => $row->status,
                'clock_in' => $row->clock_in?->format('H:i'),
                'clock_out' => $row->clock_out?->format('H:i'),
                'hours' => number_format($row->hours(), 2, '.', ''),
                'source' => $row->source,
            ])
            ->all();

        $employees = Employee::query()
            ->where('branch_id', $branch->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'designation', 'device_user_id']);

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

    private function currentBranch(Request $request): Branch
    {
        $user = $request->user('staff');

        $branch = Branch::query()->find($user->branch_id ?? (int) session('branch_id', 0));

        return $branch ?? Branch::query()->orderBy('id')->firstOrFail();
    }
}
