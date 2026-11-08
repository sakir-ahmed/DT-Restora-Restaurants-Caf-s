<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AttendanceDevice;
use App\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceDevice>
 */
class AttendanceDeviceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'name' => 'Front door',
            'serial' => fake()->bothify('ZK-####'),
            'ip_address' => null,
            'token_hash' => hash('sha256', fake()->sha256()),
            'is_active' => true,
        ];
    }
}
