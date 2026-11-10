<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    private static int $sequence = 0;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        self::$sequence += 1;

        $code = sprintf('BR%02d', self::$sequence);

        return [
            'name' => 'DT Restora Branch '.self::$sequence,
            'name_bn' => 'ডিটি রেস্টোরা শাখা '.self::$sequence,
            'code' => $code,
            'phone' => '0171100'.sprintf('%04d', self::$sequence),
            'address' => fake()->streetAddress(),
            'city' => 'Dhaka',
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
