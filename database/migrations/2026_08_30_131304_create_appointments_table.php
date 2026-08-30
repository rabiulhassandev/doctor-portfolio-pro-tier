<?php

use App\Models\Appointment;
use App\Services\Booking\BookingService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A booked appointment.
 *
 * @see Appointment
 * @see BookingService
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();

            /*
             | The patient-facing booking number, e.g. "APT-8F2K9Q".
             |
             | This is the route key, so it appears in URLs and in emails. It is
             | random rather than sequential on purpose: an incrementing id in a
             | URL invites a patient to try the number one above their own, and
             | on a page showing someone's cardiac appointment that is a
             | genuine privacy failure rather than a curiosity.
             */
            $table->string('reference', 32)->unique();

            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();

            /*
             | Snapshot of the patient's contact details as they were at the
             | moment of booking.
             |
             | Duplicating what is already on the patient row is deliberate. A
             | patient who changes their phone number next year must not
             | retroactively change the number the clinic rang for an
             | appointment last month — the appointment record is a record of
             | what happened, and the notes attached to it have to stay true.
             */
            $table->string('patient_name');
            $table->string('patient_email')->nullable();
            $table->string('patient_phone');

            /*
             | When the appointment starts, stored in UTC like every other
             | timestamp. Read it through App\Support\Clock to get clinic time.
             |
             | `dateTime` rather than `timestamp`: MySQL's TIMESTAMP type applies
             | its own implicit timezone conversion on read and write, which
             | would silently fight the UTC-storage rule, and it runs out in
             | 2038 — which is inside the lifetime of an appointment book.
             */
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');

            /*
             | Which place within the slot this booking holds, 1-based.
             |
             | A slot with max_bookings_per_slot = 3 has seats 1, 2 and 3. The
             | booking service allocates the lowest free one. Together with
             | starts_at and the guard below this is what makes double-booking
             | impossible rather than merely unlikely.
             */
            $table->unsignedTinyInteger('seat_no')->default(1);

            /*
             |------------------------------------------------------------------
             | The double-booking guard
             |------------------------------------------------------------------
             |
             | A unique index is the only thing that actually *guarantees* two
             | patients cannot take the same seat. Application-level checks lose
             | the race by definition: two requests can both read "seat 2 is
             | free" before either writes.
             |
             | But a plain unique(starts_at, seat_no) would be wrong, because a
             | cancelled appointment must release its seat for someone else, and
             | that row still exists.
             |
             | So the index includes `slot_guard`, which the model keeps in step
             | with the status (see Appointment::booted()):
             |
             |     status holds the seat  →  slot_guard = 0
             |     status released it     →  slot_guard = the row's own id
             |
             | Every live booking for a seat therefore collides on 0, while every
             | cancelled one carries a value unique to itself and collides with
             | nothing. One live booking per seat, any number of dead ones.
             |
             | Why not a MySQL generated column, which would let the database
             | derive this itself? Because it needs raw MySQL-specific DDL, and
             | this suite's tests run on SQLite — the constraint would then be
             | untested precisely where it matters most. Deriving one column from
             | another in a saving() hook is pure normalisation with no side
             | effect, which is exactly what a model hook is for.
             */
            $table->unsignedBigInteger('slot_guard')->default(0);

            // App\Enums\AppointmentStatus. A string, not a native ENUM, so a
            // future status needs no destructive column change.
            $table->string('status', 20)->default('pending');

            /*
             | While a patient is away on the payment gateway's page their seat
             | is genuinely held, so nobody else can take it out from under
             | them. If they never come back, the hold lapses and the seat is
             | released. Null means "no hold" — either already paid, or paying
             | at the chamber.
             */
            $table->dateTime('hold_expires_at')->nullable();

            // What the patient wrote when booking, and what staff wrote after.
            $table->text('notes')->nullable();
            $table->text('admin_notes')->nullable();

            /*
             | The fee as it stood when this appointment was made. Snapshotted
             | for the same reason as the contact details: raising the
             | consultation fee next month must not rewrite what a patient was
             | charged last month.
             */
            $table->decimal('fee_amount', 10, 2)->nullable();
            $table->char('currency', 3)->nullable();

            /*
             | Denormalised from the payments table so the appointment list can
             | show a paid/unpaid column without a join or an N+1. Written only
             | by App\Services\Payments\PaymentProcessor.
             */
            $table->string('payment_status', 20)->nullable();

            /*
             | When this appointment was moved, the replacement it was moved to.
             |
             | Rescheduling creates a NEW row and marks this one Rescheduled,
             | rather than editing starts_at in place. That keeps the history of
             | what the patient was originally told, and keeps the seat index
             | describing bookings that really exist.
             */
            $table->foreignId('rescheduled_to_id')
                ->nullable()
                ->constrained('appointments')
                ->nullOnDelete();

            // Stamped by AppointmentWorkflow as the status moves.
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();

            // So the reminder command can tell who has already been reminded.
            $table->timestamp('reminded_at')->nullable();

            $table->timestamps();

            /*
             | The guarantee. See the long note on slot_guard above.
             */
            $table->unique(['starts_at', 'seat_no', 'slot_guard'], 'appointments_seat_unique');

            // The admin list ("today's appointments, by status") and the
            // patient dashboard ("my upcoming ones") respectively.
            $table->index(['starts_at', 'status']);
            $table->index(['patient_id', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
