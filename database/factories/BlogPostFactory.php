<?php

namespace Database\Factories;

use App\Models\BlogPost;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BlogPost>
 */
class BlogPostFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = Str::title(fake()->unique()->sentence(6));

        return [
            'title' => $title,
            'slug' => Str::slug($title),
            'cover_image' => null,
            'excerpt' => fake()->sentence(18),
            'content' => collect(fake()->paragraphs(5))
                ->map(fn (string $p): string => "<p>{$p}</p>")
                ->implode("\n"),
            'is_published' => true,
            'published_at' => now()->subDays(fake()->numberBetween(1, 90)),
        ];
    }

    /** Written but not switched on. */
    public function draft(): static
    {
        return $this->state(fn (): array => [
            'is_published' => false,
            'published_at' => null,
        ]);
    }

    /** Switched on, but dated in the future — must stay hidden until then. */
    public function scheduled(): static
    {
        return $this->state(fn (): array => [
            'is_published' => true,
            'published_at' => now()->addWeek(),
        ]);
    }
}
