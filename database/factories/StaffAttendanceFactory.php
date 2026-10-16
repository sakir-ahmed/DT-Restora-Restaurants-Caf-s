<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\StaffAttendance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StaffAttendance>
 */
class StaffAttendanceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'employee_id' => Employee::factory(),
            'work_date' => now()->toDateString(),
            'status' => StaffAttendance::STATUS_PRESENT,
            'clock_in' => now()->setTime(9, 0),
            'clock_out' => now()->setTime(17, 0),
            'source' => StaffAttendance::SOURCE_MANUAL,
        ];
    }
}
