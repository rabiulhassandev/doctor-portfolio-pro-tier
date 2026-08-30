<?php

namespace Database\Factories;

use App\Models\Faq;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Faq>
 */
class FaqFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'question' => Str::finish(Str::title(fake()->unique()->sentence(7)), '?'),
            'answer' => fake()->paragraph(3),
            'category' => fake()->randomElement([
                'Appointments',
                'Fees and payment',
                'Your visit',
            ]),
            'is_published' => true,
            'sort_order' => 0,
        ];
    }

    public function uncategorised(): static
    {
        return $this->state(fn (): array => ['category' => null]);
    }

    public function unpublished(): static
    {
        return $this->state(fn (): array => ['is_published' => false]);
    }
}
