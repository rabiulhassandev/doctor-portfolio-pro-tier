<?php

use App\Models\AppointmentStatusLog;
use App\Services\Booking\AppointmentWorkflow;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who changed an appointment, when, and to what.
 *
 * Six columns of insurance. A clinic will eventually have a conversation that
 * starts "I never cancelled that" — and without this table the honest answer is
 * that nobody knows. Written by App\Services\Booking\AppointmentWorkflow, which
 * is the only place in the application allowed to change an appointment's
 * status, so the log cannot fall out of step with reality.
 *
 * @see AppointmentStatusLog
 * @see AppointmentWorkflow
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_status_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();

            // Null on the row that records the appointment first being created.
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);

            // App\Enums\BookingActor: admin | patient | system.
            $table->string('actor', 20)->default('system');

            /*
             | The staff member who did it, when there was one. Nullable and
             | nullOnDelete: an appointment's history must survive the departure
             | of the receptionist who handled it.
             */
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->text('reason')->nullable();

            // Only created_at is meaningful — a log entry is never updated.
            $table->timestamp('created_at')->nullable();

            $table->index(['appointment_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_status_logs');
    }
};
