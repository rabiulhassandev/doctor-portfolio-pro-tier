<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\Appointment;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'appointment_id' => Appointment::factory(),
            'gateway' => 'sslcommerz',
            'amount' => 1500,
            'currency' => 'BDT',
            'status' => PaymentStatus::Pending,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (): array => [
            'status' => PaymentStatus::Paid,
            'gateway_transaction_id' => 'TXN'.fake()->numerify('##########'),
            'paid_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (): array => [
            'status' => PaymentStatus::Failed,
            'failed_at' => now(),
        ]);
    }

    /** The patient chose to settle up in person. */
    public function atClinic(): static
    {
        return $this->state(fn (): array => [
            'gateway' => 'cash',
            'status' => PaymentStatus::DueAtClinic,
        ]);
    }
}
