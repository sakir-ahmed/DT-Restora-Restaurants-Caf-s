<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\MenuItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MenuItem>
 */
class MenuItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => ucfirst($name),
            'name_bn' => null,
            'description' => fake()->optional()->sentence(),
            'base_price' => fake()->randomFloat(2, 60, 900),
            'is_available' => true,
            'is_vegetarian' => fake()->boolean(30),
            'sort_order' => fake()->numberBetween(1, 100),
        ];
    }

    public function unavailable(): static
    {
        return $this->state(fn () => ['is_available' => false]);
    }
}
