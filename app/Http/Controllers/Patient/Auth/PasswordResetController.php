<?php

namespace App\Http\Controllers\Patient\Auth;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * "I have forgotten my password" for the patient guard.
 *
 * Uses the `patients` password broker, which has its own token table. Sharing
 * one with staff would mean a token issued for a patient's address could be
 * presented against a staff account using the same address.
 */
class PasswordResetController extends Controller
{
    /** The broker configured in config/auth.php under passwords.patients. */
    private const BROKER = 'patients';

    public function request(): View
    {
        return view('pages.patient.auth.forgot-password');
    }

    public function email(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        Password::broker(self::BROKER)->sendResetLink($request->only('email'));

        /*
         | The same answer whether or not the address exists.
         |
         | Reporting "no account with that email" turns this form into a way to
         | check whether somebody is a patient here, which on a medical site is
         | private information in itself.
         */
        return back()->with('status', 'If that email address has an account with us, we have sent it a reset link.');
    }

    public function reset(Request $request, string $token): View
    {
        return view('pages.patient.auth.reset-password', [
            'token' => $token,
            'email' => $request->string('email')->toString(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = Password::broker(self::BROKER)->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (Patient $patient, string $password): void {
                $patient->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($patient));
            },
        );

        if ($status !== Password::PasswordReset) {
            throw ValidationException::withMessages([
                'email' => 'That reset link is invalid or has expired. Please request a new one.',
            ]);
        }

        return redirect()
            ->route('patient.login')
            ->with('status', 'Your password has been changed. You can sign in with it now.');
    }
}
