<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Branch;
use App\Models\RestaurantTable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RestaurantTable>
 */
class RestaurantTableFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'name' => 'T'.fake()->unique()->numberBetween(1, 9000),
            'area' => 'Indoor',
            'seats' => 4,
            'status' => 'free',
            'is_active' => true,
        ];
    }

    public function occupied(): static
    {
        return $this->state(fn (): array => [
            'status' => 'occupied',
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
        ]);
    }
}
