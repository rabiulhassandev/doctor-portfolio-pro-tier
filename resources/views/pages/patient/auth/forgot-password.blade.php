<x-layouts.app title="Reset your password">
    <x-patient.auth-shell
        title="Forgotten your password?"
        subtitle="Tell us your email address and we will send you a link to choose a new one.">

        <form method="POST" action="{{ route('patient.password.email') }}" class="flex flex-col gap-4">
            @csrf

            <x-ui.field
                name="email"
                label="Email address"
                type="email"
                autocomplete="email"
                required
                placeholder="you@example.com" />

            <x-ui.button type="submit" size="lg" block class="mt-1">
                Send me a reset link
            </x-ui.button>
        </form>

        <x-slot:footer>
            Remembered it?
            <a href="{{ route('patient.login') }}" class="link-underline font-semibold text-brass">Sign in</a>
        </x-slot:footer>
    </x-patient.auth-shell>
</x-layouts.app>
