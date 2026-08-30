<x-layouts.app
    title="Create an account"
    description="Create a patient account to book appointments and collect your prescriptions.">

    <x-patient.auth-shell
        title="Create your account"
        subtitle="It takes about a minute, and you only have to do it once."
        :intended-slot="$intendedSlot">

        <form method="POST" action="{{ route('patient.register.store') }}" class="flex flex-col gap-4">
            @csrf

            <x-ui.field
                name="name"
                label="Your full name"
                autocomplete="name"
                required
                placeholder="Nusrat Jahan" />

            <x-ui.field
                name="phone"
                label="Mobile number"
                type="tel"
                autocomplete="tel"
                required
                placeholder="01712 345678"
                hint="The chamber uses this to confirm your appointment." />

            <x-ui.field
                name="email"
                label="Email address"
                type="email"
                autocomplete="email"
                required
                placeholder="you@example.com"
                hint="You will sign in with this." />

            <x-ui.field
                name="password"
                label="Choose a password"
                type="password"
                autocomplete="new-password"
                required
                hint="At least 8 characters." />

            <x-ui.field
                name="password_confirmation"
                label="Confirm your password"
                type="password"
                autocomplete="new-password"
                required />

            <x-ui.button type="submit" size="lg" block class="mt-2">
                Create my account
            </x-ui.button>

            <p class="text-center text-sm leading-relaxed text-muted">
                Your details are used only to manage your care at this chamber.
            </p>
        </form>

        <x-slot:footer>
            Already have an account?
            <a href="{{ route('patient.login') }}" class="link-underline font-semibold text-brand">Sign in</a>
        </x-slot:footer>
    </x-patient.auth-shell>
</x-layouts.app>
