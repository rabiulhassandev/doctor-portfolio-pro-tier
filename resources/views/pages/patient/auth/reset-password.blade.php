<x-layouts.app title="Choose a new password">
    <x-patient.auth-shell
        title="Choose a new password"
        subtitle="Pick something you have not used elsewhere.">

        <form method="POST" action="{{ route('patient.password.update') }}" class="flex flex-col gap-4">
            @csrf

            {{-- Both carried through from the emailed link. --}}
            <input type="hidden" name="token" value="{{ $token }}">

            <x-ui.field
                name="email"
                label="Email address"
                type="email"
                autocomplete="email"
                required
                :value="$email" />

            <x-ui.field
                name="password"
                label="New password"
                type="password"
                autocomplete="new-password"
                required
                hint="At least 8 characters." />

            <x-ui.field
                name="password_confirmation"
                label="Confirm your new password"
                type="password"
                autocomplete="new-password"
                required />

            <x-ui.button type="submit" size="lg" block class="mt-1">
                Save my new password
            </x-ui.button>
        </form>
    </x-patient.auth-shell>
</x-layouts.app>
