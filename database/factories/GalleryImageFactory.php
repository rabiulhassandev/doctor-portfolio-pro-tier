<?php

namespace Database\Factories;

use App\Models\GalleryImage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GalleryImage>
 */
class GalleryImageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'image' => 'gallery/placeholder.jpg',
            'caption' => fake()->sentence(5),
            'alt_text' => fake()->sentence(8),
            'sort_order' => 0,
        ];
    }
}
