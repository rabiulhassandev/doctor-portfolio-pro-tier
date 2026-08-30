<?php

use App\Models\Appointment;
use App\Models\AvailabilityBlackout;
use App\Models\AvailabilitySlot;
use App\Models\BlogPost;
use App\Models\DoctorProfile;
use App\Models\Faq;
use App\Models\GalleryImage;
use App\Models\HealthVideo;
use App\Models\MedicalDocument;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Service;
use App\Models\Testimonial;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| Admin panel
|--------------------------------------------------------------------------
|
| These render every screen with real records in the database. A resource can
| register its route perfectly and still fail the moment a column callback
| runs, so listing the routes proves very little on its own.
|
*/

beforeEach(function () {
    freezeClinicClock();
    $this->staff = User::factory()->create();
});

describe('access control', function () {
    it('sends a stranger to the login page', function () {
        $this->get('/admin')->assertRedirect('/admin/login');
    });

    it('lets a staff member in', function () {
        $this->actingAs($this->staff)->get('/admin')->assertOk();
    });

    it('does not let a patient into the admin panel', function () {
        // The patient guard is a different guard entirely; a patient session
        // must not satisfy the panel's `auth` check.
        $patient = Patient::factory()->create();

        $this->actingAs($patient, 'patient')
            ->get('/admin')
            ->assertRedirect('/admin/login');
    });
});

describe('every screen renders', function () {
    beforeEach(function () {
        // One record of everything, so column callbacks and badges actually run
        // against data rather than an empty table.
        DoctorProfile::create(['name' => 'Dr. Test', 'specialization' => 'Cardiology']);
        Service::factory()->create();
        Testimonial::factory()->create();
        BlogPost::factory()->create();
        GalleryImage::factory()->create();
        Faq::factory()->create();
        HealthVideo::factory()->create();
        AvailabilitySlot::factory()->create();
        AvailabilityBlackout::factory()->create();

        $this->appointment = Appointment::factory()->create();
        Payment::factory()->paid()->create(['appointment_id' => $this->appointment->id]);
        MedicalDocument::factory()->create(['patient_id' => $this->appointment->patient_id]);

        $this->actingAs($this->staff);
    });

    it('renders the dashboard with its widgets', function () {
        $this->get('/admin')->assertOk();
    });

    it('renders every list screen', function (string $path) {
        $this->get("/admin/{$path}")->assertOk();
    })->with([
        'appointments',
        'availability-slots',
        'availability-blackouts',
        'payments',
        'patients',
        'medical-documents',
        'services',
        'testimonials',
        'blog-posts',
        'gallery-images',
        'faqs',
        'health-videos',
        'doctor-profile-settings',
    ]);

    it('renders every create form', function (string $path) {
        $this->get("/admin/{$path}/create")->assertOk();
    })->with([
        'availability-slots',
        'availability-blackouts',
        'medical-documents',
        'services',
        'testimonials',
        'blog-posts',
        'gallery-images',
        'faqs',
        'health-videos',
    ]);

    it('renders the appointment detail page', function () {
        $this->get('/admin/appointments/'.$this->appointment->getRouteKey())
            ->assertOk()
            ->assertSee($this->appointment->patient_name)
            ->assertSee($this->appointment->reference);
    });

    it('renders the patient detail page', function () {
        $this->get('/admin/patients/'.$this->appointment->patient_id)->assertOk();
    });
});

describe('resources that must not be creatable by hand', function () {
    it('offers no create route for appointments', function () {
        // Hand-typed appointments would bypass capacity and seat allocation,
        // which is the machinery that prevents double-booking.
        $this->actingAs($this->staff)->get('/admin/appointments/create')->assertNotFound();
    });

    it('offers no create route for payments', function () {
        $this->actingAs($this->staff)->get('/admin/payments/create')->assertNotFound();
    });

    it('offers no create route for patients', function () {
        $this->actingAs($this->staff)->get('/admin/patients/create')->assertNotFound();
    });
});
