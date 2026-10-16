<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\JobOpening;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobOpening>
 */
class JobOpeningFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->jobTitle(),
            'department' => 'Kitchen',
            'employment_type' => 'full_time',
            'summary' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'requirements' => fake()->sentence(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => ['closes_on' => now()->subDay()->toDateString()]);
    }
}
