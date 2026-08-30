<?php

namespace Database\Factories;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentStatus;
use App\Models\Appointment;
use App\Models\Patient;
use App\Support\Clock;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // A round half-hour tomorrow evening, in clinic time, stored as UTC.
        $startsAt = Clock::today()->addDay()->setTime(18, 0);

        return [
            'patient_id' => Patient::factory(),
            'patient_name' => fn (array $attributes) => Patient::find($attributes['patient_id'])?->name ?? fake()->name(),
            'patient_email' => fn (array $attributes) => Patient::find($attributes['patient_id'])?->email ?? fake()->safeEmail(),
            'patient_phone' => fn (array $attributes) => Patient::find($attributes['patient_id'])?->phone ?? '+8801700000000',
            'starts_at' => $startsAt->utc(),
            'ends_at' => $startsAt->addMinutes(30)->utc(),
            'seat_no' => 1,
            'status' => AppointmentStatus::Pending,
            'fee_amount' => 1500,
            'currency' => 'BDT',
            'payment_status' => PaymentStatus::DueAtClinic,
        ];
    }

    /** Book the appointment at a specific clinic-time moment. */
    public function at(CarbonImmutable $startsAt, int $durationMinutes = 30): static
    {
        return $this->state(fn (): array => [
            'starts_at' => $startsAt->utc(),
            'ends_at' => $startsAt->addMinutes($durationMinutes)->utc(),
        ]);
    }

    public function seat(int $seatNo): static
    {
        return $this->state(fn (): array => [
            'seat_no' => $seatNo,
        ]);
    }

    public function status(AppointmentStatus $status): static
    {
        return $this->state(fn (): array => [
            'status' => $status,
        ]);
    }

    public function confirmed(): static
    {
        return $this->state(fn (): array => [
            'status' => AppointmentStatus::Confirmed,
            'confirmed_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (): array => [
            'status' => AppointmentStatus::Completed,
            'confirmed_at' => now(),
            'completed_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (): array => [
            'status' => AppointmentStatus::Cancelled,
            'cancelled_at' => now(),
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (): array => [
            'status' => AppointmentStatus::Confirmed,
            'confirmed_at' => now(),
            'payment_status' => PaymentStatus::Paid,
        ]);
    }

    /** A seat held while a payment completes, whose window has already lapsed. */
    public function expiredHold(): static
    {
        return $this->state(fn (): array => [
            'status' => AppointmentStatus::Pending,
            'payment_status' => PaymentStatus::Pending,
            'hold_expires_at' => now()->subMinute(),
        ]);
    }

    /** A seat currently held while the patient is on the gateway page. */
    public function onHold(): static
    {
        return $this->state(fn (): array => [
            'status' => AppointmentStatus::Pending,
            'payment_status' => PaymentStatus::Pending,
            'hold_expires_at' => now()->addMinutes(15),
        ]);
    }

    public function past(): static
    {
        $startsAt = Clock::today()->subWeek()->setTime(18, 0);

        return $this->at($startsAt)->completed();
    }
}
