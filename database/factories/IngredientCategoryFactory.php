<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\IngredientCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IngredientCategory>
 */
class IngredientCategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->unique()->word()),
            'name_bn' => null,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
