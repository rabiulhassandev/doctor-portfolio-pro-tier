<?php

namespace Database\Seeders;

use App\Enums\AppointmentStatus;
use App\Enums\BookingActor;
use App\Enums\DocumentKind;
use App\Enums\PaymentStatus;
use App\Models\Appointment;
use App\Models\AvailabilitySlot;
use App\Models\DoctorProfile;
use App\Models\MedicalDocument;
use App\Models\Patient;
use App\Models\Payment;
use App\Services\Booking\AvailabilityService;
use App\Support\Clock;
use App\Support\Slot;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Demo patients, with appointments in every state.
 *
 * >>> These people are invented. Their telephone numbers end in zeroes and
 * >>> their emails use the reserved `.example` domain, so a seeded demo site
 * >>> cannot reach anybody real.
 *
 * The point of this seeder is that a buyer installing the template sees a
 * working practice rather than an empty one: a dashboard with numbers on it, a
 * list with today's patients in it, and a patient account that has something in
 * it to look at.
 *
 * Appointments are created directly rather than through BookingService, because
 * the service sends email. Seeding a demo should not put forty messages in
 * anybody's outbox.
 */
class PatientSeeder extends Seeder
{
    /** The account documented in the README, so a buyer can sign in at once. */
    public const DEMO_EMAIL = 'patient@example.com';

    public const DEMO_PASSWORD = 'password';

    public function run(): void
    {
        $patients = $this->createPatients();

        // No availability rules means no slots to book into.
        if (! AvailabilitySlot::query()->active()->exists()) {
            return;
        }

        $this->createUpcomingAppointments($patients);
        $this->createPastAppointments($patients->first());
    }

    /**
     * @return Collection<int, Patient>
     */
    private function createPatients(): Collection
    {
        $people = [
            ['name' => 'Sharmin Akter', 'email' => self::DEMO_EMAIL, 'phone' => '+8801700000001'],
            ['name' => 'Rezaul Karim', 'email' => 'rezaul@example.com', 'phone' => '+8801700000002'],
            ['name' => 'Nasreen Haque', 'email' => 'nasreen@example.com', 'phone' => '+8801700000003'],
            ['name' => 'Imran Sarker', 'email' => 'imran@example.com', 'phone' => '+8801700000004'],
        ];

        return collect($people)->map(fn (array $person): Patient => Patient::query()->updateOrCreate(
            ['email' => $person['email']],
            [
                ...$person,
                'password' => Hash::make(self::DEMO_PASSWORD),
                'email_verified_at' => now(),
                'is_active' => true,
            ],
        ));
    }

    /**
     * Fill some of the next few days, leaving plenty free to book into.
     *
     * @param  Collection<int, Patient>  $patients
     */
    private function createUpcomingAppointments(Collection $patients): void
    {
        $availability = app(AvailabilityService::class);

        // Real slots from the real rules, so the seeded bookings and the
        // booking calendar can never disagree with each other.
        $slots = $availability->slotsForRange(Clock::today(), Clock::today()->addDays(7))
            ->flatten(1)
            ->values();

        if ($slots->isEmpty()) {
            return;
        }

        $plan = [
            ['patient' => 0, 'status' => AppointmentStatus::Confirmed, 'payment' => PaymentStatus::Paid],
            ['patient' => 1, 'status' => AppointmentStatus::Pending, 'payment' => PaymentStatus::DueAtClinic],
            ['patient' => 2, 'status' => AppointmentStatus::Confirmed, 'payment' => PaymentStatus::DueAtClinic],
            ['patient' => 3, 'status' => AppointmentStatus::Pending, 'payment' => PaymentStatus::DueAtClinic],
        ];

        foreach ($plan as $index => $entry) {
            /** @var Slot|null $slot */
            $slot = $slots->get($index * 2);   // Space them out.

            if (! $slot) {
                continue;
            }

            $patient = $patients[$entry['patient']];

            $appointment = $this->makeAppointment($patient, $slot, $entry['status'], $entry['payment']);

            if ($entry['payment'] === PaymentStatus::Paid) {
                Payment::query()->updateOrCreate(
                    ['appointment_id' => $appointment->getKey()],
                    [
                        'gateway' => 'sslcommerz',
                        'amount' => $appointment->fee_amount,
                        'currency' => $appointment->currency,
                        'status' => PaymentStatus::Paid,
                        'gateway_transaction_id' => 'DEMO'.Str::upper(Str::random(8)),
                        'paid_at' => now()->subDay(),
                    ],
                );
            }
        }
    }

    /**
     * A completed visit last week, with a prescription attached.
     *
     * This is what makes the patient dashboard worth looking at on a fresh
     * install — an account with nothing in it demonstrates nothing.
     */
    private function createPastAppointments(Patient $patient): void
    {
        $startsAt = Clock::today()->subDays(7)->setTime(18, 30);

        $appointment = Appointment::query()->updateOrCreate(
            [
                'patient_id' => $patient->getKey(),
                'starts_at' => $startsAt->utc(),
                'seat_no' => 1,
            ],
            [
                'patient_name' => $patient->name,
                'patient_email' => $patient->email,
                'patient_phone' => $patient->phone,
                'ends_at' => $startsAt->addMinutes(30)->utc(),
                'status' => AppointmentStatus::Completed,
                'notes' => 'Blood pressure has been high on home readings for about two months.',
                'admin_notes' => 'Started on a low dose. Review in six weeks with a home diary.',
                'fee_amount' => DoctorProfile::current()->consultation_fee,
                'currency' => config('booking.payment.currency', 'BDT'),
                'payment_status' => PaymentStatus::Paid,
                'confirmed_at' => $startsAt->subDays(2),
                'completed_at' => $startsAt->addMinutes(30),
            ],
        );

        $appointment->statusLogs()->firstOrCreate(
            ['to_status' => AppointmentStatus::Completed],
            [
                'from_status' => AppointmentStatus::Confirmed,
                'actor' => BookingActor::Admin,
                'created_at' => $startsAt->addMinutes(35),
            ],
        );

        $this->attachDemoDocument($patient, $appointment);
    }

    /**
     * A prescription the demo patient can actually download.
     *
     * Written straight to the private `medical` disk. A tiny valid PDF is
     * generated in code rather than committed, so the repository carries no
     * file that looks like a real medical record.
     */
    private function attachDemoDocument(Patient $patient, Appointment $appointment): void
    {
        $path = 'patients/'.$patient->getKey().'/demo-prescription.pdf';

        Storage::disk('medical')->put($path, $this->minimalPdf());

        MedicalDocument::query()->updateOrCreate(
            ['path' => $path],
            [
                'patient_id' => $patient->getKey(),
                'appointment_id' => $appointment->getKey(),
                'title' => 'Prescription — '.$appointment->startsAtLocal()->format('j F Y'),
                'kind' => DocumentKind::Prescription,
                'disk' => 'medical',
                'original_filename' => 'prescription.pdf',
                'mime_type' => 'application/pdf',
                'size_bytes' => strlen($this->minimalPdf()),
                'is_visible_to_patient' => true,
            ],
        );
    }

    /** The smallest thing a PDF reader will open, so the demo download works. */
    private function minimalPdf(): string
    {
        return "%PDF-1.4\n"
            ."1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n"
            ."2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n"
            ."3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 595 842]>>endobj\n"
            ."trailer<</Root 1 0 R>>\n"
            .'%%EOF';
    }

    private function makeAppointment(
        Patient $patient,
        Slot $slot,
        AppointmentStatus $status,
        PaymentStatus $paymentStatus,
    ): Appointment {
        return Appointment::query()->updateOrCreate(
            [
                'patient_id' => $patient->getKey(),
                'starts_at' => $slot->startsAt->utc(),
                'seat_no' => 1,
            ],
            [
                'patient_name' => $patient->name,
                'patient_email' => $patient->email,
                'patient_phone' => $patient->phone,
                'ends_at' => $slot->endsAt->utc(),
                'status' => $status,
                'fee_amount' => DoctorProfile::current()->consultation_fee,
                'currency' => config('booking.payment.currency', 'BDT'),
                'payment_status' => $paymentStatus,
                'confirmed_at' => $status === AppointmentStatus::Confirmed ? now() : null,
            ],
        );
    }
}
