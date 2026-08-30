<?php

use App\Models\AvailabilityBlackout;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Days the doctor is not seeing anyone: holidays, conferences, Eid.
 *
 * A blackout beats everything. However many availability rules cover a date —
 * weekly, date-specific, replacing or extending — a blackout across it means
 * zero bookable slots. Having one rule that always wins is what makes "I am
 * away next week" a safe thing for a doctor to enter at eleven at night.
 *
 * Stored as an inclusive date *range* rather than one row per day, so "away
 * from the 10th to the 20th" is a single entry the doctor can later edit or
 * delete as one thing.
 *
 * @see AvailabilityBlackout
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('availability_blackouts', function (Blueprint $table) {
            $table->id();

            $table->date('starts_on');

            // Inclusive. Equal to starts_on for a single day off.
            $table->date('ends_on');

            /*
             | Shown to patients on the booking calendar — "Eid holiday" is a
             | far better explanation for a greyed-out week than nothing at all,
             | and it stops them ringing the chamber to ask.
             |
             | Nullable, because a doctor is entitled to be unavailable without
             | telling the internet why.
             */
            $table->string('reason')->nullable();

            $table->timestamps();

            // The lookup is always "does any range cover this date?".
            $table->index(['starts_on', 'ends_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('availability_blackouts');
    }
};
