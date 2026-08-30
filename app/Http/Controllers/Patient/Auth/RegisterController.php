<?php

namespace App\Http\Controllers\Patient\Auth;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Patient registration.
 *
 * Hand-written rather than scaffolded, for two reasons: the views belong in the
 * site's own design system rather than in a package's, and the account created
 * here must land on the `patient` guard — never in the `users` table that can
 * reach the admin panel.
 */
class RegisterController extends Controller
{
    public function create(Request $request): View
    {
        return view('pages.patient.auth.register', [
            // Set when a guest picked a slot and was asked to sign in first, so
            // we can send them straight back to finish booking it.
            'intendedSlot' => $request->session()->get('booking.slot'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:patients,email'],
            /*
             | Required, not optional. This is the number the chamber actually
             | rings to confirm or move an appointment — the email address is a
             | courtesy, the phone number is how the practice works.
             */
            'phone' => ['required', 'string', 'max:30'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ], [
            'email.unique' => 'An account already exists with that email. Try signing in instead.',
        ]);

        $patient = Patient::create($data);

        Auth::guard('patient')->login($patient, remember: true);

        // Rotate the session id now that the visitor's privileges have changed.
        $request->session()->regenerate();

        return $this->redirectAfterAuth($request)
            ->with('status', 'Welcome, '.$patient->name.'. Your account is ready.');
    }

    /**
     * Send the new patient wherever they were trying to go.
     *
     * Almost always the booking page: registering is something people do in
     * order to do something else, and dropping them on a dashboard they did not
     * ask for makes them find their way back to the slot they had chosen.
     */
    protected function redirectAfterAuth(Request $request): RedirectResponse
    {
        if ($request->session()->has('booking.slot')) {
            return redirect()->route('booking');
        }

        return redirect()->intended(route('patient.dashboard'));
    }
}
