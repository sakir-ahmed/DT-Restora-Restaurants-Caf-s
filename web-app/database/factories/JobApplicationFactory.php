<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\JobApplication;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobApplication>
 */
class JobApplicationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'mobile' => '8801711'.fake()->numerify('######'),
            'email' => fake()->safeEmail(),
            'message' => fake()->sentence(),
            'status' => 'new',
        ];
    }
}
