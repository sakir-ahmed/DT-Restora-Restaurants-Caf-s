<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Attendance\RegisterAttendanceDeviceAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAttendanceDeviceRequest;
use App\Models\AttendanceDevice;
use App\Models\Branch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AttendanceDeviceController extends Controller
{
    public function store(StoreAttendanceDeviceRequest $request, RegisterAttendanceDeviceAction $action): RedirectResponse
    {
        $branch = $this->currentBranch($request);
        $result = $action->execute($branch, $request->validated());

        return back()
            ->with('success', __('attendance.device_created'))
            ->with('device_token', $result['token'])
            ->with('device_name', $result['device']->name);
    }

    public function update(StoreAttendanceDeviceRequest $request, AttendanceDevice $device): RedirectResponse
    {
        $this->assertDeviceBranch($request, $device);

        $device->update([
            'name' => $request->validated('name'),
            'serial' => $request->validated('serial'),
            'ip_address' => $request->validated('ip_address'),
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('success', __('attendance.device_updated'));
    }

    public function rotate(Request $request, AttendanceDevice $device, RegisterAttendanceDeviceAction $action): RedirectResponse
    {
        $this->authorize('attendance.manage');
        $this->assertDeviceBranch($request, $device);

        $result = $action->rotate($device);

        return back()
            ->with('success', __('attendance.token_rotated'))
            ->with('device_token', $result['token'])
            ->with('device_name', $result['device']->name);
    }

    private function assertDeviceBranch(Request $request, AttendanceDevice $device): void
    {
        $branch = $this->currentBranch($request);

        abort_unless($device->branch_id === $branch->id, 404);
        abort_unless($request->user('staff')?->canAccessBranch($device->branch_id) ?? false, 404);
    }

    private function currentBranch(Request $request): Branch
    {
        $user = $request->user('staff');

        $branch = Branch::query()->find($user->branch_id ?? (int) session('branch_id', 0));

        return $branch ?? Branch::query()->orderBy('id')->firstOrFail();
    }
}
