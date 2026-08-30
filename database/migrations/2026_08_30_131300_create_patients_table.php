<?php

use App\Models\Patient;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * People who book appointments.
 *
 * Patients are a separate table from `users`, and authenticate on a separate
 * guard. This is a security boundary, not a stylistic choice: `users` is the
 * staff table and holds accounts that can reach the admin panel at /admin. If
 * the two shared a table, then every patient registration would be creating a
 * row in the same place staff accounts live, and one mistaken `canAccessPanel()`
 * would hand the practice's whole appointment book to whoever signed up last.
 *
 * Two tables, two guards, no shared surface. See config/auth.php.
 *
 * @see Patient
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('email')->unique();

            /*
             | Not unique, and not optional.
             |
             | Not optional because this is the number the chamber actually
             | rings to confirm or move an appointment — an email address is a
             | courtesy, a phone number is how the practice works.
             |
             | Not unique because families share one phone. A mother booking for
             | herself and for two children under their own names is the normal
             | case, and a unique constraint here would tell her the second
             | child "already has an account".
             */
            $table->string('phone');

            $table->string('password');

            $table->date('date_of_birth')->nullable();
            $table->string('gender', 20)->nullable();

            // Useful to have on screen when the doctor opens an appointment.
            $table->text('address')->nullable();
            $table->text('medical_notes')->nullable();

            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamp('last_login_at')->nullable();

            /*
             | Lets the practice block an account that is abusing the booking
             | form without deleting the appointment history attached to it.
             */
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index('phone');
        });

        /*
         | Password resets for the patient guard.
         |
         | A separate table from `password_reset_tokens` (which belongs to the
         | staff `users` guard) for the same reason the accounts are separate:
         | the two token pools must never be able to unlock each other's
         | accounts, even if an email address happens to exist in both.
         */
        Schema::create('patient_password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_password_reset_tokens');
        Schema::dropIfExists('patients');
    }
};
