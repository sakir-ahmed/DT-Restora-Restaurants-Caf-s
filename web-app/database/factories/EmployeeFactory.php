<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PayBasis;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $salary = fake()->randomFloat(2, 12000, 60000);

        return [
            'name' => fake()->name(),
            'designation' => fake()->randomElement(['Chef', 'Waiter', 'Cashier', 'Cleaner', 'Helper', 'Rider']),
            'mobile' => '880'.fake()->numerify('01#########'),
            'monthly_salary' => $salary,
            'pay_basis' => PayBasis::Monthly,
            'pay_rate' => $salary,
            'joined_at' => fake()->optional()->date(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
