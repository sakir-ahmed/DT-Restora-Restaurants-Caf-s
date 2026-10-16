<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Media;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Media>
 */
class MediaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kind' => Media::KIND_IMAGE,
            'path' => 'media/'.fake()->uuid().'.jpg',
            'title' => fake()->words(3, true),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    public function video(string $url = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'): static
    {
        return $this->state(fn (): array => ['kind' => Media::KIND_VIDEO, 'path' => null, 'video_url' => $url]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
