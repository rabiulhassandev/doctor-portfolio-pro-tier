<?php

use App\Models\DoctorProfile;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The doctor profile is a *singleton* table: it always holds exactly one row.
 *
 * Everything that identifies the practice — the doctor's name, photo, bio,
 * contact details, opening hours, consultation fee and social links — lives
 * here, so the site can be rebranded from the admin panel without touching a
 * line of code.
 *
 * @see DoctorProfile
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctor_profiles', function (Blueprint $table) {
            $table->id();

            // --- Identity -----------------------------------------------------
            $table->string('name');                       // "Dr. Amelia Hart"
            $table->string('specialization');             // "Consultant Cardiologist"

            /*
             | The register a doctor is on depends entirely on where they
             | practise, so the label is stored beside the number rather than
             | hard-coded. A patient in Bangladesh checks a BMDC number the way
             | a British patient checks a GMC number. Both nullable, so a market
             | that uses neither simply renders nothing.
             */
            $table->string('registration_label')->nullable();   // "BMDC Reg. No."
            $table->string('registration_number')->nullable();  // "A-42817"

            // The name on the sign outside, which is often not the doctor's own.
            $table->string('chamber_name')->nullable();

            $table->string('tagline')->nullable();        // Short hero strapline.
            $table->string('photo')->nullable();          // Path on the `public` disk.

            // Nullable because it is genuinely optional — a doctor who leaves the
            // field blank should not be recorded as having zero years' experience,
            // and the public pages hide the figure entirely when it is unset.
            $table->unsignedSmallInteger('years_of_experience')->nullable();

            // --- Long-form content -------------------------------------------
            $table->text('short_bio')->nullable();        // 1–2 sentences, used on the home page.
            $table->longText('bio')->nullable();          // Full bio, rendered on /about.
            $table->longText('philosophy')->nullable();   // "My approach to care" section.

            /**
             * Qualifications as a JSON list so the doctor can add and remove
             * rows in the admin panel without a migration:
             * [{ "title": "MBBS", "institution": "…", "year": "2005" }, …]
             */
            $table->json('qualifications')->nullable();

            // --- Contact ------------------------------------------------------
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('whatsapp')->nullable();       // Digits only, e.g. 14155550132.
            $table->string('address_line')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country')->nullable();

            // Map coordinates power the schema.org markup and the map embed.
            $table->decimal('map_latitude', 10, 7)->nullable();
            $table->decimal('map_longitude', 10, 7)->nullable();
            $table->text('map_embed_url')->nullable();    // Optional Google Maps <iframe> src.

            /**
             * Opening hours as JSON, one entry per weekday:
             * [{ "day": "monday", "opens": "09:00", "closes": "17:00", "is_closed": false }, …]
             *
             * These are the *published* hours shown on the contact page. They
             * are not the same thing as bookable availability, which lives in
             * `availability_slots` — a chamber may open at six and only take
             * booked patients from seven.
             */
            $table->json('working_hours')->nullable();

            /**
             * Social profiles keyed by network:
             * { "facebook": "https://…", "instagram": "https://…", … }
             */
            $table->json('social_links')->nullable();

            // --- Booking (Pro tier) -------------------------------------------

            /*
             | The standard consultation fee, shown on the booking screen and
             | snapshotted onto each appointment as it is made. Nullable so a
             | practice that would rather discuss fees in person can leave it
             | blank, in which case no price appears anywhere.
             */
            $table->decimal('consultation_fee', 10, 2)->nullable();

            /*
             | Overrides config('booking.horizon_days') when set, so the doctor
             | can shorten or extend how far ahead patients may book without a
             | developer. Resolved in exactly one place — see
             | AvailabilityService::horizonEnd().
             */
            $table->unsignedSmallInteger('booking_horizon_days')->nullable();

            // Shown on the booking confirmation screen and in the email:
            // "please arrive ten minutes early and bring your previous reports".
            $table->text('booking_instructions')->nullable();

            // --- SEO defaults (per-page values can still override these) ------
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 500)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_profiles');
    }
};
