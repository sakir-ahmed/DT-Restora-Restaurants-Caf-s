<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\SalaryAdvance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalaryAdvance>
 */
class SalaryAdvanceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'employee_id' => Employee::factory(),
            'amount' => fake()->randomFloat(2, 100, 5000),
            'advanced_on' => now()->toDateString(),
            'note' => null,
        ];
    }
}
