<?php

namespace Database\Factories;

use App\Enums\VideoType;
use App\Models\HealthVideo;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<HealthVideo>
 */
class HealthVideoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = Str::title(fake()->unique()->sentence(5));

        return [
            'title' => $title,
            'slug' => Str::slug($title),
            'description' => fake()->paragraph(4),
            'topic' => fake()->randomElement([
                'Heart failure',
                'Blood pressure',
                'After your angiogram',
                'Cholesterol',
                'Living with diabetes',
            ]),
            'video_type' => VideoType::Youtube,
            'source_url' => 'https://www.youtube.com/watch?v='.Str::random(11),
            'duration_seconds' => fake()->numberBetween(90, 900),
            'is_featured' => false,
            'is_published' => true,
            'published_at' => now()->subDays(fake()->numberBetween(1, 60)),
            'sort_order' => 0,
        ];
    }

    /**
     * A video hosted on this site rather than embedded.
     *
     * `source_url` is cleared: an upload has no URL to parse, and leaving one
     * would send the model's normaliser down the embed path.
     */
    public function uploaded(string $path = 'videos/example.mp4'): static
    {
        return $this->state(fn (): array => [
            'video_type' => VideoType::Upload,
            'source_url' => null,
            'video_path' => $path,
            'thumbnail_path' => 'videos/thumbnails/example.jpg',
        ]);
    }

    public function vimeo(string $id = '76979871'): static
    {
        return $this->state(fn (): array => [
            'video_type' => VideoType::Vimeo,
            'source_url' => "https://vimeo.com/{$id}",
        ]);
    }

    public function topic(string $topic): static
    {
        return $this->state(fn (): array => ['topic' => $topic]);
    }

    public function featured(): static
    {
        return $this->state(fn (): array => ['is_featured' => true]);
    }

    public function draft(): static
    {
        return $this->state(fn (): array => ['is_published' => false]);
    }
}
