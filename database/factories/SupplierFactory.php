<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'mobile' => '880'.fake()->numerify('01#########'),
            'email' => fake()->optional()->safeEmail(),
            'address' => fake()->optional()->streetAddress(),
            'is_active' => true,
        ];
    }
}
