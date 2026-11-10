<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AttendanceDevice;
use App\Models\AttendancePunch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendancePunch>
 */
class AttendancePunchFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'attendance_device_id' => AttendanceDevice::factory(),
            'device_user_id' => (string) fake()->numberBetween(1, 500),
            'punched_at' => now()->setTime(9, 0),
        ];
    }
}
