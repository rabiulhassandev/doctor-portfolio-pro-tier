<?php

use App\Models\AvailabilitySlot;
use App\Services\Booking\SlotGenerator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The doctor's availability *rules* — not individual bookable slots.
 *
 * One row here says something like "every Sunday, 6pm to 9pm, in half-hour
 * appointments, two patients per slot". Expanding that into the actual list of
 * bookable times for a given date is the job of App\Services\Booking\SlotGenerator,
 * and it happens at request time.
 *
 * Storing rules rather than pre-generated slots is what keeps the booking
 * horizon free: a practice can take bookings ninety days out without ninety
 * days of rows existing, and changing the evening start time updates every
 * future date at once instead of needing a bulk edit.
 *
 * @see AvailabilitySlot
 * @see SlotGenerator
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('availability_slots', function (Blueprint $table) {
            $table->id();

            /*
             | App\Enums\AvailabilityScope: 'weekly' | 'date'.
             |
             | Exactly one of day_of_week / specific_date is filled in, and this
             | column says which. See the enum for why it is not simply derived
             | from the two nullable columns below.
             |
             | The invariant is enforced in the Filament form and in the model's
             | saving() hook rather than by a CHECK constraint: MySQL 5.7 parses
             | CHECK and then silently ignores it, so the constraint would be a
             | lie on some buyers' hosting.
             */
            $table->string('scope', 10)->default('weekly');

            // 0 = Sunday … 6 = Saturday, matching Carbon::dayOfWeek.
            $table->unsignedTinyInteger('day_of_week')->nullable();

            $table->date('specific_date')->nullable();

            // Clinic wall-clock times. See App\Support\Clock for the timezone rules.
            $table->time('start_time');
            $table->time('end_time');

            // Length of one appointment, in minutes.
            $table->unsignedSmallInteger('slot_duration')->default(30);

            /*
             | How many patients may book the same time.
             |
             | One is a true appointment system. Chambers that run a serial
             | system — everyone told to arrive at six, seen in order — set this
             | to four or six instead, which is how a great many practices here
             | actually work.
             */
            $table->unsignedTinyInteger('max_bookings_per_slot')->default(1);

            /*
             | Only meaningful when scope = 'date'.
             |
             | true  — this date's rules REPLACE the normal weekly ones.
             |         ("On the 14th I only sit 10am to noon.")  The common case.
             | false — this date's rules are ADDED to the weekly ones.
             |         ("On the 14th I'm also doing an extra evening block.")
             */
            $table->boolean('replaces_recurring')->default(true);

            // "Evening chamber", "Saturday morning clinic". Admin-only label.
            $table->string('label')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // The two shapes AvailabilityService queries in.
            $table->index(['scope', 'is_active', 'day_of_week']);
            $table->index(['scope', 'is_active', 'specific_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('availability_slots');
    }
};
