<?php

use App\Filament\Widgets\PracticeOverview;
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
use Livewire\Livewire;

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

describe('the sign-in screen', function () {
    /*
     | The one screen in the panel that wears the PUBLIC site's identity: deep
     | navy, brass and a photograph of the chamber. Everything behind the login
     | form goes back to being the bright blue working tool.
     |
     | The render hooks that do it are scoped to Filament's Login page, and
     | that scoping is the whole design — these two tests are what stop it
     | quietly becoming global.
     */

    it('wears the public palette and a photograph', function () {
        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('--login-brass:'.config('site.colors.brass'), escape: false)
            ->assertSee('--login-night:'.config('site.colors.night'), escape: false)
            ->assertSee('login-brand', escape: false)
            // A way out, or the screen is a dead end for anyone who arrived at
            // /admin by mistake.
            ->assertSee('Back to the website');
    });

    it('keeps that palette off every other screen in the panel', function () {
        $this->actingAs($this->staff)
            ->get('/admin')
            ->assertOk()
            ->assertDontSee('--login-brass', escape: false)
            ->assertDontSee(config('site.colors.brass'), escape: false)
            ->assertDontSee('login-brand', escape: false);
    });
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

    it('dresses the panel in the admin palette, not the public one', function () {
        /*
         | The two palettes are deliberately different: a bright blue working
         | tool here, a dark brass-accented website out there. This asserts the
         | panel is actually wearing its own, because a rebrand that
         | accidentally pointed both at one array would still render fine and
         | just look wrong.
         */
        $response = $this->get('/admin')->assertOk();

        $response->assertSee('--brand-canvas:'.config('site.admin.canvas'), escape: false)
            ->assertSee('--brand-sidebar:'.config('site.admin.sidebar'), escape: false)
            // The public site's brass must not appear anywhere in the panel.
            ->assertDontSee(config('site.colors.brass'), escape: false);
    });

    it('shows the footer credit line', function () {
        $this->get('/admin')
            ->assertOk()
            ->assertSee('admin-footer', escape: false)
            ->assertSee(config('site.name'));
    });

    it('gives every dashboard stat the icon the theme enlarges', function () {
        /*
         | The big translucent mark on each blue card is Stat::icon(), pulled
         | out of the label row and scaled up by theme.css. Without it every
         | card is an anonymous blue rectangle.
         |
         | Asserted against the widget rather than the dashboard page, because
         | Filament renders widgets as deferred Livewire components — they are
         | not in the dashboard's first response at all.
         */
        $html = Livewire::test(PracticeOverview::class)
            ->assertSee("Today's appointments")
            ->assertSee('Waiting for confirmation')
            ->html();

        // Four cards, and an icon inside every one of them.
        $cards = substr_count($html, 'fi-wi-stats-overview-stat-label-ctn');

        expect($cards)->toBe(4)
            ->and(substr_count($html, '<svg'))->toBeGreaterThanOrEqual($cards);
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
