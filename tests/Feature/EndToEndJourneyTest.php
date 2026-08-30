<?php

use App\Enums\AppointmentStatus;
use App\Enums\BookingActor;
use App\Enums\DocumentKind;
use App\Enums\PaymentStatus;
use App\Exceptions\SlotUnavailableException;
use App\Livewire\BookingWizard;
use App\Livewire\VideoLibrary;
use App\Models\Appointment;
use App\Models\AvailabilityBlackout;
use App\Models\HealthVideo;
use App\Models\MedicalDocument;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\AppointmentBookedDoctor;
use App\Notifications\AppointmentBookedPatient;
use App\Notifications\PaymentReceiptPatient;
use App\Services\Booking\AppointmentWorkflow;
use App\Services\Booking\AvailabilityService;
use App\Services\Booking\BookingData;
use App\Services\Booking\BookingService;
use App\Support\Clock;
use Database\Seeders\AvailabilitySeeder;
use Database\Seeders\DoctorProfileSeeder;
use Database\Seeders\HealthVideoSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| The whole journey, once, through the real HTTP stack
|--------------------------------------------------------------------------
|
| Each phase of this application has its own focused tests. This file exists
| to prove the seams between them hold: that a patient really can go from a
| cold visit to a booked, paid appointment with a prescription in their
| account, and that the doctor sees the same appointment from the other side.
|
| It runs against the SHIPPED SEEDERS rather than against hand-built fixtures,
| so it also proves a fresh install is genuinely usable out of the box.
|
| ---------------------------------------------------------------------------
| A note on actingAs(), which bites in exactly this kind of test
| ---------------------------------------------------------------------------
|
| `actingAs($user)` with no guard argument does NOT reset to the application's
| default guard — it keeps whichever guard the previous actingAs() selected.
| So once this test has acted as a patient, a later `actingAs($staff)` would
| quietly put the staff member on the `patient` guard, and every admin page
| would redirect to the login screen.
|
| Every actingAs() below therefore names its guard explicitly.
|
*/

beforeEach(function () {
    freezeClinicClock('2026-09-01 09:00:00');
    Cache::flush();
    Notification::fake();
    Storage::fake('medical');

    // Exactly what a buyer gets from `php artisan migrate --seed`.
    $this->seed(DoctorProfileSeeder::class);
    $this->seed(AvailabilitySeeder::class);

    config()->set('booking.payment.gateways.sslcommerz.store_id', 'testbox');
    config()->set('booking.payment.gateways.sslcommerz.store_password', 'testpass');
});

it('takes a patient from stranger to paid appointment with a prescription', function () {

    // =====================================================================
    // 1. A stranger browses the site
    // =====================================================================
    $this->get(route('home'))->assertOk();
    $this->get(route('booking'))->assertOk();

    // =====================================================================
    // 2. They pick a time as a guest, and are stopped at the door
    // =====================================================================
    $availability = app(AvailabilityService::class);
    $slot = $availability->slotsForRange(Clock::today(), Clock::today()->addDays(7))
        ->flatten(1)
        ->first();

    expect($slot)->not->toBeNull('The shipped availability seeder should produce bookable slots.');

    Livewire::test(BookingWizard::class)
        ->call('selectDate', $slot->startsAt->toDateString())
        ->call('selectSlot', $slot->key())
        ->assertSet('step', BookingWizard::STEP_DETAILS)
        ->assertSee('Sign in to confirm');

    // The chosen time is parked so it survives the trip to registration.
    expect(session('booking.slot'))->toBe($slot->key());

    // =====================================================================
    // 3. They register
    // =====================================================================
    $this->post(route('patient.register.store'), [
        'name' => 'Ayesha Rahman',
        'email' => 'ayesha@example.com',
        'phone' => '01712345678',
        'password' => 'correct-horse-battery',
        'password_confirmation' => 'correct-horse-battery',
    ])->assertRedirect(route('booking'));   // Back to finish what they started.

    $this->assertAuthenticated('patient');
    $patient = Patient::query()->where('email', 'ayesha@example.com')->firstOrFail();

    // =====================================================================
    // 4. The wizard picks their slot back up, and they book it
    // =====================================================================
    Livewire::actingAs($patient, 'patient')
        ->test(BookingWizard::class)
        ->assertSet('selectedSlot', $slot->key())
        ->assertSet('step', BookingWizard::STEP_DETAILS)
        ->set('notes', 'Breathless climbing stairs for two months.')
        ->call('continueToPayment')
        // Both gateways are configured, so a real choice is offered.
        ->assertSet('step', BookingWizard::STEP_PAYMENT)
        ->set('gateway', 'sslcommerz')
        ->call('confirm')
        ->assertSet('handingOffToGateway', true);

    $appointment = Appointment::query()->where('patient_id', $patient->id)->firstOrFail();

    expect($appointment->notes)->toBe('Breathless climbing stairs for two months.')
        ->and($appointment->status)->toBe(AppointmentStatus::Pending)
        // The seat is held only while they are away paying.
        ->and($appointment->payment_status)->toBe(PaymentStatus::Pending)
        ->and($appointment->hold_expires_at)->not->toBeNull();

    Notification::assertSentOnDemand(AppointmentBookedPatient::class);
    Notification::assertSentOnDemand(AppointmentBookedDoctor::class);

    // That time is now gone from the public calendar.
    expect($availability->findBookableSlot($slot->startsAt))->toBeNull();

    // =====================================================================
    // 5. They pay
    // =====================================================================
    Http::fake([
        '*gwprocess*' => Http::response([
            'status' => 'SUCCESS',
            'sessionkey' => 'SESSION-E2E',
            'GatewayPageURL' => 'https://sandbox.sslcommerz.com/pay/e2e',
        ]),
    ]);

    $this->actingAs($patient, 'patient')
        ->post(route('payments.start', $appointment), ['gateway' => 'sslcommerz'])
        ->assertRedirect('https://sandbox.sslcommerz.com/pay/e2e');

    $payment = Payment::query()->where('appointment_id', $appointment->id)->firstOrFail();

    // The gateway sends them back, and we verify it with the gateway's own API.
    Http::fake([
        '*validationserverAPI*' => Http::response([
            'status' => 'VALID',
            'tran_id' => $payment->reference,
            'amount' => (string) $payment->amount,
            'currency' => $payment->currency,
            'bank_tran_id' => 'BANK-E2E-1',
        ]),
    ]);

    $this->post(route('payments.callback', ['sslcommerz', 'success']), [
        'tran_id' => $payment->reference,
        'val_id' => 'VAL-E2E',
    ])->assertRedirect(route('patient.appointments.show', $appointment));

    $appointment->refresh();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Paid)
        // Paying confirms the appointment regardless of the default status.
        ->and($appointment->status)->toBe(AppointmentStatus::Confirmed)
        ->and($appointment->payment_status)->toBe(PaymentStatus::Paid)
        ->and($appointment->hold_expires_at)->toBeNull();

    Notification::assertSentOnDemand(PaymentReceiptPatient::class);

    // =====================================================================
    // 6. The doctor sees it from the other side
    // =====================================================================

    /*
     | A fresh session, because this is a different person at a different
     | computer — and mechanically too: the patient's login left their own
     | password hash in the session, and Filament's AuthenticateSession
     | middleware would log the staff guard back out rather than trust a
     | session established by somebody else.
     */
    $this->flushSession();

    $staff = User::factory()->create();

    $this->actingAs($staff, 'web')
        ->get('/admin/appointments/'.$appointment->getRouteKey())
        ->assertOk()
        ->assertSee('Ayesha Rahman')
        ->assertSee($appointment->reference);

    // And the dashboard renders with real data behind its widgets.
    $this->actingAs($staff, 'web')->get('/admin')->assertOk();

    // =====================================================================
    // 7. The doctor sees the patient, then issues a prescription
    // =====================================================================
    app(AppointmentWorkflow::class)->complete($appointment->fresh(), BookingActor::Admin);

    $path = 'patients/'.$patient->id.'/prescription.pdf';
    Storage::disk('medical')->put($path, '%PDF-1.4 prescription');

    $document = MedicalDocument::create([
        'patient_id' => $patient->id,
        'appointment_id' => $appointment->id,
        'title' => 'Prescription',
        'kind' => DocumentKind::Prescription,
        'disk' => 'medical',
        'path' => $path,
        'original_filename' => 'prescription.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 22,
        'uploaded_by_user_id' => $staff->id,
        'is_visible_to_patient' => true,
    ]);

    // =====================================================================
    // 8. The patient collects it
    // =====================================================================

    // Back to the patient's own browser.
    $this->flushSession();

    $this->actingAs($patient, 'patient')
        ->get(route('patient.dashboard'))
        ->assertOk()
        ->assertSee('Prescription');

    $this->actingAs($patient, 'patient')
        ->get(route('documents.download', $document))
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    expect($document->fresh()->download_count)->toBe(1);

    // …and nobody else can.
    $this->actingAs(Patient::factory()->create(), 'patient')
        ->get(route('documents.download', $document))
        ->assertForbidden();
});

it('lets a patient browse and watch the education library', function () {
    $this->seed(HealthVideoSeeder::class);

    $this->get(route('videos.index'))
        ->assertOk()
        ->assertSee('Blood pressure')
        ->assertSee('Understanding heart failure');

    $video = HealthVideo::query()->where('video_type', 'youtube')->firstOrFail();

    $html = $this->get(route('videos.show', $video))
        ->assertOk()
        ->assertSee('"@type":"VideoObject"', escape: false)
        ->getContent();

    /*
     | The click-to-load facade.
     |
     | The iframe markup IS in the source, but only inside a <template>, whose
     | contents a browser does not render and whose resources it does not
     | fetch. That is what stops a page of videos pulling half a megabyte of
     | player code — and setting a tracking cookie — before anybody presses
     | play.
     |
     | So the assertion is not "no iframe anywhere"; it is "no iframe outside
     | a template".
     */
    $outsideTemplates = preg_replace('~<template[^>]*>.*?</template>~s', '', $html);

    expect($outsideTemplates)->not->toContain('<iframe')
        ->and($html)->toContain('youtube-nocookie.com')
        // And the poster button the visitor actually sees.
        ->and($outsideTemplates)->toContain('Play ');

    // Filtering narrows the grid.
    Livewire::test(VideoLibrary::class)
        ->set('topic', 'Blood pressure')
        ->assertSee('What high blood pressure actually does')
        ->assertDontSee('Understanding heart failure')
        ->set('topic', '')
        ->set('search', 'heart failure')
        ->assertSee('Understanding heart failure')
        ->assertDontSee('What happens during an echocardiogram');
});

it('refuses to double-book the last remaining seat', function () {
    $availability = app(AvailabilityService::class);
    $slot = $availability->slotsForRange(Clock::today(), Clock::today()->addDays(7))->flatten(1)->first();

    $first = Patient::factory()->create();
    $second = Patient::factory()->create();

    $booking = app(BookingService::class);

    $booking->book(new BookingData($first, $slot->startsAt));

    // The seeded rules allow one patient per time, so the second must be
    // turned away — with a message written for a patient, not a 500.
    expect(fn () => $booking->book(new BookingData($second, $slot->startsAt)))
        ->toThrow(SlotUnavailableException::class);

    expect(Appointment::query()->blocking()->count())->toBe(1);
});

it('closes the calendar on the days the doctor is away', function () {
    $availability = app(AvailabilityService::class);

    // The seeder puts a two-day conference a fortnight out.
    $blackout = AvailabilityBlackout::query()->firstOrFail();
    $awayDate = Clock::parse($blackout->starts_on->toDateString());

    expect($availability->slotsForDate($awayDate))->toBeEmpty()
        ->and($availability->blackoutFor($awayDate)?->reason)->toBe('Away at a conference')
        ->and($availability->bookableDates()->contains(
            fn ($date) => $date->isSameDay($awayDate),
        ))->toBeFalse();
});
