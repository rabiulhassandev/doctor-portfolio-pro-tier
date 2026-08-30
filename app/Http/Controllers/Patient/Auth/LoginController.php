<?php

namespace App\Http\Controllers\Patient\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Signing patients in and out.
 *
 * Authenticates on the `patient` guard only. A patient session can never
 * satisfy an admin route, and vice versa — see config/auth.php.
 */
class LoginController extends Controller
{
    /** How many failed attempts before an account is locked for a minute. */
    private const MAX_ATTEMPTS = 5;

    public function create(Request $request): View
    {
        return view('pages.patient.auth.login', [
            'intendedSlot' => $request->session()->get('booking.slot'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $this->ensureIsNotRateLimited($request);

        if (! Auth::guard('patient')->attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey($request));

            throw ValidationException::withMessages([
                // Deliberately vague: saying "no account with that email" tells
                // an attacker which addresses are registered here, and on a
                // medical site the mere fact of registration is private.
                'email' => 'Those details do not match our records.',
            ]);
        }

        /*
         | A blocked account authenticates correctly — the password is right —
         | so the check has to come after, and the session has to be thrown away
         | again immediately.
         */
        if (! Auth::guard('patient')->user()->is_active) {
            Auth::guard('patient')->logout();
            $request->session()->invalidate();

            throw ValidationException::withMessages([
                'email' => 'This account has been suspended. Please telephone the chamber.',
            ]);
        }

        RateLimiter::clear($this->throttleKey($request));

        // Rotate the session id on privilege change, which is what stops a
        // session-fixation attack.
        $request->session()->regenerate();

        Auth::guard('patient')->user()->forceFill(['last_login_at' => now()])->save();

        if ($request->session()->has('booking.slot')) {
            return redirect()->route('booking');
        }

        return redirect()->intended(route('patient.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('patient')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('status', 'You have been signed out.');
    }

    /**
     * Throttle per email AND per IP together.
     *
     * Keying on the email alone would let one attacker lock a real patient out
     * of their own account simply by guessing at it repeatedly.
     *
     * @throws ValidationException
     */
    protected function ensureIsNotRateLimited(Request $request): void
    {
        if (RateLimiter::tooManyAttempts($this->throttleKey($request), self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($this->throttleKey($request));

            throw ValidationException::withMessages([
                'email' => 'Too many attempts. Please wait '.ceil($seconds / 60).' minute(s) and try again.',
            ]);
        }
    }

    protected function throttleKey(Request $request): string
    {
        return Str::transliterate(Str::lower((string) $request->input('email')).'|'.$request->ip());
    }
}
