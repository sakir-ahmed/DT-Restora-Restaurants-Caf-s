<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Letter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Letter>
 */
class LetterFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'title' => fake()->sentence(4),
            'recipient' => fake()->name(),
            'subject' => fake()->sentence(6),
            'body' => fake()->paragraph(),
            'letter_date' => now()->toDateString(),
        ];
    }
}
