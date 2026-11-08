<?php

declare(strict_types=1);

namespace App\Actions\Attendance;

use App\Models\AttendanceDevice;
use App\Models\Branch;
use App\Models\Scopes\BranchScope;
use Illuminate\Support\Str;

class RegisterAttendanceDeviceAction
{
    /**
     * @param  array{name: string, serial?: string|null, ip_address?: string|null, is_active?: bool}  $data
     * @return array{device: AttendanceDevice, token: string}
     */
    public function execute(Branch $branch, array $data): array
    {
        $token = Str::random(48);

        $device = AttendanceDevice::query()->create([
            'branch_id' => $branch->id,
            'name' => $data['name'],
            'serial' => $data['serial'] ?? null,
            'ip_address' => $data['ip_address'] ?? null,
            'token_hash' => hash('sha256', $token),
            'is_active' => $data['is_active'] ?? true,
        ]);

        return ['device' => $device, 'token' => $token];
    }

    /**
     * @return array{device: AttendanceDevice, token: string}
     */
    public function rotate(AttendanceDevice $device): array
    {
        $token = Str::random(48);
        $device->update(['token_hash' => hash('sha256', $token)]);

        return ['device' => $device->refresh(), 'token' => $token];
    }

    public function findByToken(string $plainToken): ?AttendanceDevice
    {
        if ($plainToken === '') {
            return null;
        }

        return AttendanceDevice::query()
            ->withoutGlobalScope(BranchScope::class)
            ->where('token_hash', hash('sha256', $plainToken))
            ->where('is_active', true)
            ->first();
    }
}
