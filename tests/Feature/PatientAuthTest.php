<?php

use App\Models\Appointment;
use App\Models\DoctorProfile;
use App\Models\MedicalDocument;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| Patient accounts
|--------------------------------------------------------------------------
|
| The security boundary this tier rests on: patients and staff are separate
| tables on separate guards, and one patient must never reach another's
| records.
|
*/

beforeEach(function () {
    freezeClinicClock();

    DoctorProfile::create([
        'name' => 'Dr. Test',
        'specialization' => 'Cardiology',
        'phone' => '+8801700000000',
    ]);
    DoctorProfile::forgetCurrent();
});

describe('registration', function () {
    it('creates an account on the patient guard and signs them in', function () {
        $response = $this->post(route('patient.register.store'), [
            'name' => 'Nusrat Jahan',
            'email' => 'nusrat@example.com',
            'phone' => '01712345678',
            'password' => 'correct-horse-battery',
            'password_confirmation' => 'correct-horse-battery',
        ]);

        $response->assertRedirect(route('patient.dashboard'));

        $this->assertAuthenticated('patient');
        // Crucially NOT in the staff table.
        $this->assertDatabaseHas('patients', ['email' => 'nusrat@example.com']);
        $this->assertDatabaseMissing('users', ['email' => 'nusrat@example.com']);
    });

    it('refuses an email that is already registered', function () {
        Patient::factory()->create(['email' => 'taken@example.com']);

        $this->post(route('patient.register.store'), [
            'name' => 'Someone Else',
            'email' => 'taken@example.com',
            'phone' => '01712345678',
            'password' => 'correct-horse-battery',
            'password_confirmation' => 'correct-horse-battery',
        ])->assertSessionHasErrors('email');
    });

    it('requires a phone number, because that is how the chamber calls back', function () {
        $this->post(route('patient.register.store'), [
            'name' => 'No Phone',
            'email' => 'nophone@example.com',
            'password' => 'correct-horse-battery',
            'password_confirmation' => 'correct-horse-battery',
        ])->assertSessionHasErrors('phone');
    });

    it('renders the form', function () {
        $this->get(route('patient.register'))->assertOk()->assertSee('Create your account');
    });
});

describe('signing in', function () {
    beforeEach(function () {
        $this->patient = Patient::factory()->create([
            'email' => 'nusrat@example.com',
            'password' => Hash::make('correct-horse-battery'),
        ]);
    });

    it('signs a patient in and stamps the time', function () {
        $this->post(route('patient.login.store'), [
            'email' => 'nusrat@example.com',
            'password' => 'correct-horse-battery',
        ])->assertRedirect(route('patient.dashboard'));

        $this->assertAuthenticatedAs($this->patient, 'patient');
        expect($this->patient->fresh()->last_login_at)->not->toBeNull();
    });

    it('does not reveal whether an email is registered here', function () {
        /*
         | On a medical site, confirming that somebody has an account IS private
         | information in itself — so an unknown address and a wrong password
         | must produce the identical answer, word for word.
         */
        $message = 'Those details do not match our records.';

        $this->post(route('patient.login.store'), [
            'email' => 'nobody@example.com',
            'password' => 'whatever',
        ])->assertSessionHasErrors(['email' => $message]);

        $this->post(route('patient.login.store'), [
            'email' => 'nusrat@example.com',
            'password' => 'wrong',
        ])->assertSessionHasErrors(['email' => $message]);

        $this->assertGuest('patient');
    });

    it('refuses a blocked account even with the right password', function () {
        $this->patient->forceFill(['is_active' => false])->save();

        $this->post(route('patient.login.store'), [
            'email' => 'nusrat@example.com',
            'password' => 'correct-horse-battery',
        ])->assertSessionHasErrors('email');

        $this->assertGuest('patient');
    });

    it('locks out after repeated failures', function () {
        foreach (range(1, 5) as $attempt) {
            $this->post(route('patient.login.store'), [
                'email' => 'nusrat@example.com',
                'password' => 'wrong',
            ]);
        }

        $this->post(route('patient.login.store'), [
            'email' => 'nusrat@example.com',
            'password' => 'correct-horse-battery',
        ])->assertSessionHasErrors('email');

        expect(session('errors')->first('email'))->toContain('Too many attempts');
    });

    it('signs them out again', function () {
        $this->actingAs($this->patient, 'patient')
            ->post(route('patient.logout'))
            ->assertRedirect(route('home'));

        $this->assertGuest('patient');
    });
});

describe('the dashboard', function () {
    beforeEach(function () {
        $this->patient = Patient::factory()->create();
    });

    it('is closed to visitors who are not signed in', function () {
        $this->get(route('patient.dashboard'))->assertRedirect(route('patient.login'));
    });

    it('does not accept a STAFF session', function () {
        // The two guards are separate; a staff login must not open the patient
        // area any more than a patient login opens /admin.
        $this->actingAs(User::factory()->create())
            ->get(route('patient.dashboard'))
            ->assertRedirect(route('patient.login'));
    });

    it('renders every patient page', function (string $routeName) {
        $this->actingAs($this->patient, 'patient')
            ->get(route($routeName))
            ->assertOk();
    })->with([
        'patient.dashboard',
        'patient.appointments.index',
        'patient.documents.index',
        'patient.profile',
    ]);

    it('shows the patient their own appointments', function () {
        $mine = Appointment::factory()->create(['patient_id' => $this->patient->id]);

        $this->actingAs($this->patient, 'patient')
            ->get(route('patient.dashboard'))
            ->assertOk()
            ->assertSee($mine->reference);
    });
});

describe('one patient must never see another patient', function () {
    beforeEach(function () {
        $this->patient = Patient::factory()->create();
        $this->stranger = Patient::factory()->create();
    });

    it('refuses someone else\'s appointment', function () {
        $theirs = Appointment::factory()->create(['patient_id' => $this->stranger->id]);

        $this->actingAs($this->patient, 'patient')
            ->get(route('patient.appointments.show', $theirs))
            ->assertForbidden();
    });

    it('refuses to cancel someone else\'s appointment', function () {
        $theirs = Appointment::factory()->create(['patient_id' => $this->stranger->id]);

        $this->actingAs($this->patient, 'patient')
            ->post(route('patient.appointments.cancel', $theirs))
            ->assertForbidden();
    });

    it('lists only their own documents', function () {
        MedicalDocument::factory()->create([
            'patient_id' => $this->stranger->id,
            'title' => 'Somebody else\'s blood test',
        ]);

        $this->actingAs($this->patient, 'patient')
            ->get(route('patient.documents.index'))
            ->assertOk()
            ->assertDontSee('Somebody else');
    });
});

describe('medical document downloads', function () {
    beforeEach(function () {
        Storage::fake('medical');

        $this->patient = Patient::factory()->create();
        $this->document = MedicalDocument::factory()->create(['patient_id' => $this->patient->id]);

        Storage::disk('medical')->put($this->document->path, '%PDF-1.4 fake');
    });

    it('lets the patient download their own document', function () {
        $this->actingAs($this->patient, 'patient')
            ->get(route('documents.download', $this->document))
            ->assertOk()
            // Without nosniff, a lab's .html report becomes stored XSS on the
            // clinic's own origin.
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        expect($this->document->fresh()->download_count)->toBe(1);
    });

    it('refuses a stranger', function () {
        $this->actingAs(Patient::factory()->create(), 'patient')
            ->get(route('documents.download', $this->document))
            ->assertForbidden();
    });

    it('refuses someone who is not signed in at all', function () {
        $this->get(route('documents.download', $this->document))->assertForbidden();
    });

    it('refuses a document the doctor has not released yet', function () {
        $staged = MedicalDocument::factory()->hidden()->create(['patient_id' => $this->patient->id]);

        $this->actingAs($this->patient, 'patient')
            ->get(route('documents.download', $staged))
            ->assertForbidden();
    });

    it('lets staff download it', function () {
        $this->actingAs(User::factory()->create())
            ->get(route('documents.download', $this->document))
            ->assertOk();
    });

    it('keeps the file out of the public storage path', function () {
        /*
         | The medical disk lives outside public/ with serve => false, so
         | Laravel registers no route that can reach it. Guessing the path under
         | /storage must not return the file — whether that is refused as 403 or
         | 404 is the framework's business; what matters is that no byte of the
         | document comes back.
         */
        $response = $this->get('/storage/'.$this->document->path);

        expect($response->status())->not->toBe(200)
            ->and($response->getContent())->not->toContain('%PDF');
    });
});
