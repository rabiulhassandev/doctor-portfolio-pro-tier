<x-layouts.app title="My details">
    <x-patient.shell
        title="My details"
        subtitle="Keep these up to date so the chamber can reach you.">

        <form method="POST" action="{{ route('patient.profile.update') }}" class="flex max-w-2xl flex-col gap-6">
            @csrf
            @method('PATCH')

            <x-ui.card padding="loose">
                <div class="flex flex-col gap-5">
                    <h2 class="text-lg font-semibold text-ink">About you</h2>

                    <x-ui.field name="name" label="Full name" required :value="$patient->name" autocomplete="name" />

                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-ui.field
                            name="phone"
                            label="Mobile number"
                            type="tel"
                            required
                            :value="$patient->phone"
                            autocomplete="tel" />

                        <x-ui.field
                            name="email"
                            label="Email address"
                            type="email"
                            required
                            :value="$patient->email"
                            autocomplete="email"
                            hint="You sign in with this." />

                        <x-ui.field
                            name="date_of_birth"
                            label="Date of birth"
                            type="date"
                            :value="$patient->date_of_birth?->toDateString()" />

                        <x-ui.field
                            name="gender"
                            label="Gender"
                            as="select"
                            placeholder="Prefer not to say"
                            :value="$patient->gender"
                            :options="['male' => 'Male', 'female' => 'Female', 'other' => 'Other']" />
                    </div>

                    <x-ui.field
                        name="address"
                        label="Address"
                        as="textarea"
                        :rows="3"
                        :value="$patient->address" />
                </div>
            </x-ui.card>

            <x-ui.card padding="loose">
                <div class="flex flex-col gap-5">
                    <div class="flex flex-col gap-1">
                        <h2 class="text-lg font-semibold text-ink">Change your password</h2>
                        <p class="text-[0.9375rem] text-muted">Leave these empty to keep your current password.</p>
                    </div>

                    <x-ui.field
                        name="current_password"
                        label="Current password"
                        type="password"
                        autocomplete="current-password" />

                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-ui.field
                            name="password"
                            label="New password"
                            type="password"
                            autocomplete="new-password"
                            hint="At least 8 characters." />

                        <x-ui.field
                            name="password_confirmation"
                            label="Confirm new password"
                            type="password"
                            autocomplete="new-password" />
                    </div>
                </div>
            </x-ui.card>

            <div class="flex flex-wrap items-center gap-3">
                <x-ui.button type="submit" size="lg">Save my details</x-ui.button>

                <p class="text-sm leading-relaxed text-muted">
                    Changing these does not alter appointments you have already booked.
                </p>
            </div>
        </form>
    </x-patient.shell>
</x-layouts.app>
