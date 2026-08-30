<?php

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\AvailabilityBlackout;
use App\Models\AvailabilitySlot;
use App\Models\DoctorProfile;
use App\Models\HealthVideo;
use App\Models\Patient;
use App\Support\Clock;
use Illuminate\Database\QueryException;

/*
|--------------------------------------------------------------------------
| Schema smoke tests
|--------------------------------------------------------------------------
|
| These cover the load-bearing invariants of the database layer — the ones
| that would be expensive to discover were broken after the booking flow was
| built on top of them. Behavioural tests for slot generation live alongside
| AvailabilityService.
|
*/

it('creates a patient with a hashed password on the patient guard', function () {
    $patient = Patient::factory()->create(['email' => 'Nadia@Example.com']);

    // The saving() hook lowercases emails so one address cannot become two accounts.
    expect($patient->fresh()->email)->toBe('nadia@example.com')
        ->and($patient->password)->not->toBe('password')
        ->and(Hash::check('password', $patient->password))->toBeTrue();
});

it('gives every appointment an unguessable reference', function () {
    $appointment = Appointment::factory()->create();

    expect($appointment->reference)
        ->toStartWith('APT-')
        ->and($appointment->reference)->toHaveLength(10)
        // No O/0 or I/L/1 confusion when read down the phone.
        ->and($appointment->reference)->not->toContain('O')
        ->and($appointment->reference)->not->toContain('I');
});

it('stores appointment times in UTC but reads them back in clinic time', function () {
    freezeClinicClock('2026-09-01 09:00:00');

    // 6pm at the chamber.
    $localStart = Clock::today()->addDay()->setTime(18, 0);

    $appointment = Appointment::factory()->at($localStart)->create();

    // Stored as UTC — for Asia/Dhaka (UTC+6) that is noon.
    expect($appointment->fresh()->starts_at->format('H:i'))->toBe('12:00')
        // But the patient is always told six o'clock.
        ->and($appointment->startsAtLocal()->format('g:i A'))->toBe('6:00 PM');
});

describe('the double-booking guard', function () {
    it('refuses a second live booking for the same seat', function () {
        freezeClinicClock();
        $startsAt = Clock::today()->addDay()->setTime(18, 0);

        Appointment::factory()->at($startsAt)->seat(1)->create();

        // The unique index is the guarantee — not an application check, which
        // two simultaneous requests could both pass.
        expect(fn () => Appointment::factory()->at($startsAt)->seat(1)->create())
            ->toThrow(QueryException::class);
    });

    it('allows a different seat at the same time', function () {
        freezeClinicClock();
        $startsAt = Clock::today()->addDay()->setTime(18, 0);

        Appointment::factory()->at($startsAt)->seat(1)->create();
        Appointment::factory()->at($startsAt)->seat(2)->create();

        expect(Appointment::query()->count())->toBe(2);
    });

    it('frees the seat again once a booking is cancelled', function () {
        freezeClinicClock();
        $startsAt = Clock::today()->addDay()->setTime(18, 0);

        $first = Appointment::factory()->at($startsAt)->seat(1)->create();

        // Cancelling moves slot_guard off 0, so the seat stops colliding.
        $first->markStatus(AppointmentStatus::Cancelled);
        $first->save();

        Appointment::factory()->at($startsAt)->seat(1)->create();

        expect(Appointment::query()->blocking()->count())->toBe(1)
            ->and($first->fresh()->slot_guard)->toBe($first->id);
    });
});

it('normalises any pasted YouTube URL down to a bare id', function () {
    $video = HealthVideo::factory()->create([
        'source_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=42s',
    ]);

    expect($video->video_id)->toBe('dQw4w9WgXcQ')
        // Embeds go through the no-cookie host on a medical site.
        ->and($video->embedUrl())->toContain('youtube-nocookie.com/embed/dQw4w9WgXcQ')
        ->and($video->thumbnailUrl())->toBe('https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg');
});

it('keeps availability rules honest about their scope', function () {
    // A weekly rule must not keep a stale specific_date that the date query
    // would then match on.
    $rule = AvailabilitySlot::factory()->weeklyOn(0)->create(['specific_date' => '2026-09-14']);

    expect($rule->fresh()->specific_date)->toBeNull();

    $dated = AvailabilitySlot::factory()->onDate('2026-09-14')->create(['day_of_week' => 3]);

    expect($dated->fresh()->day_of_week)->toBeNull();
});

it('swaps a blackout range entered backwards', function () {
    $blackout = AvailabilityBlackout::factory()->between('2026-09-20', '2026-09-10')->create();

    expect($blackout->fresh()->starts_on->toDateString())->toBe('2026-09-10')
        ->and($blackout->fresh()->ends_on->toDateString())->toBe('2026-09-20');
});

it('falls back to config when no doctor profile has been saved yet', function () {
    // A fresh install must still render rather than blowing up on null.
    expect(DoctorProfile::current()->exists)->toBeFalse()
        ->and(DoctorProfile::current()->name)->toBe(config('site.name'));
});

it('caches the doctor profile for the request and busts it on save', function () {
    DoctorProfile::create(['name' => 'Dr. First', 'specialization' => 'Cardiology']);
    DoctorProfile::forgetCurrent();

    expect(DoctorProfile::current()->name)->toBe('Dr. First');

    DoctorProfile::first()->update(['name' => 'Dr. Second']);

    // saved() clears the container binding, so the next read is fresh.
    expect(DoctorProfile::current()->name)->toBe('Dr. Second');
});
