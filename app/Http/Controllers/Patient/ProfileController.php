<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * A patient editing their own details.
 *
 * Note that changing anything here does NOT rewrite existing appointments:
 * those carry a snapshot of the contact details as they were when the booking
 * was made, because an appointment is a record of what happened.
 */
class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('pages.patient.profile', [
            'patient' => $request->user('patient'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $patient = $request->user('patient');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'string', 'email', 'max:255',
                Rule::unique('patients', 'email')->ignore($patient->getKey()),
            ],
            'phone' => ['required', 'string', 'max:30'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:500'],

            // Optional: only validated when they are actually changing it.
            'current_password' => ['nullable', 'required_with:password', 'current_password:patient'],
            'password' => ['nullable', 'confirmed', Password::defaults()],
        ], [
            'current_password.current_password' => 'That is not your current password.',
            'current_password.required_with' => 'Please enter your current password to change it.',
        ]);

        $patient->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'gender' => $data['gender'] ?? null,
            'address' => $data['address'] ?? null,
        ]);

        if (filled($data['password'] ?? null)) {
            $patient->password = Hash::make($data['password']);
        }

        $patient->save();

        return back()->with('status', 'Your details have been saved.');
    }
}
