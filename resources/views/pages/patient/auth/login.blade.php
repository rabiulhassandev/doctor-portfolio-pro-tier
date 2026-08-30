<x-layouts.app
    title="Sign in"
    description="Sign in to your patient account to manage your appointments.">

    <x-patient.auth-shell
        title="Welcome back"
        subtitle="Sign in to see your appointments and documents."
        :intended-slot="$intendedSlot">

        <form method="POST" action="{{ route('patient.login.store') }}" class="flex flex-col gap-4">
            @csrf

            <x-ui.field
                name="email"
                label="Email address"
                type="email"
                autocomplete="email"
                required
                placeholder="you@example.com" />

            <x-ui.field
                name="password"
                label="Password"
                type="password"
                autocomplete="current-password"
                required />

            <div class="flex flex-wrap items-center justify-between gap-3">
                <label class="flex cursor-pointer items-center gap-2 text-sm text-muted">
                    <input type="checkbox"
                           name="remember"
                           value="1"
                           @checked(old('remember'))
                           class="size-4 rounded border-line-strong text-ink focus:outline-2 focus:outline-offset-2 focus:outline-brass/40">
                    Keep me signed in
                </label>

                <a href="{{ route('patient.password.request') }}" class="link-underline text-sm font-medium text-ink">
                    Forgotten your password?
                </a>
            </div>

            <x-ui.button type="submit" size="lg" block class="mt-2">
                Sign in
            </x-ui.button>
        </form>

        <x-slot:footer>
            New here?
            <a href="{{ route('patient.register') }}" class="link-underline font-semibold text-brass">Create an account</a>
        </x-slot:footer>
    </x-patient.auth-shell>
</x-layouts.app>
