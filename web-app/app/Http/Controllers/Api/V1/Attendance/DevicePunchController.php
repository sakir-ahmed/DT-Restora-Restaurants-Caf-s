<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Attendance;

use App\Actions\Attendance\RecordDevicePunchAction;
use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\StoreDevicePunchRequest;
use App\Models\AttendanceDevice;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

class DevicePunchController extends Controller
{
    /**
     * Inbound punch from a ZKTeco attendance device, or from a small bridge
     * that forwards the device's punches.
     *
     * Auth: `Authorization: Bearer {device token}` issued when the device
     * is registered under Attendance. The token is not a staff login.
     *
     * Body:
     * - device_user_id: string, required. The user id enrolled on the device.
     * - punched_at: string, required. ISO-8601 datetime.
     * - direction: "in" or "out", optional. Omitted punches alternate:
     *   the first punch of the day clocks in, a later punch clocks out.
     *
     * Idempotent on the device, device_user_id and punched_at (to the second).
     */
    public function store(StoreDevicePunchRequest $request, RecordDevicePunchAction $action): JsonResponse
    {
        /** @var AttendanceDevice $device */
        $device = $request->attributes->get('attendance_device');

        $attendance = $action->execute(
            $device,
            $request->string('device_user_id')->toString(),
            CarbonImmutable::parse($request->input('punched_at')),
            $request->input('direction'),
        );

        return $this->success([
            'attendance_id' => $attendance->id,
            'employee_id' => $attendance->employee_id,
            'work_date' => $attendance->work_date->toDateString(),
            'status' => $attendance->status,
            'clock_in' => $attendance->clock_in?->toIso8601String(),
            'clock_out' => $attendance->clock_out?->toIso8601String(),
        ]);
    }
}
