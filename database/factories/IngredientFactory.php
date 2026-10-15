<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Ingredient;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ingredient>
 */
class IngredientFactory extends Factory
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
            'unit_id' => Unit::query()->inRandomOrder()->value('id') ?? Unit::factory()->create()->id,
            'reorder_level' => 0,
            'is_active' => true,
        ];
    }
}
