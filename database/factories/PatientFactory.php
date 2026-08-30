<?php

namespace Database\Factories;

use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<Patient>
 */
class PatientFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            /*
             | Reserved documentation range, so a seeded demo site can never
             | text or ring a real person by accident.
             */
            'phone' => '+8801'.fake()->numerify('#########'),
            'password' => Hash::make('password'),
            'date_of_birth' => fake()->dateTimeBetween('-70 years', '-18 years'),
            'gender' => fake()->randomElement(['male', 'female']),
            'address' => fake()->streetAddress().', Dhaka',
            'email_verified_at' => now(),
            'remember_token' => Str::random(10),
            'is_active' => true,
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes): array => [
            'email_verified_at' => null,
        ]);
    }

    /** A patient the clinic has blocked from booking. */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
