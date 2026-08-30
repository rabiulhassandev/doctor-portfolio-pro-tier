{{--
    "Taking you to the payment page…"

    Some gateways require a form POST rather than a plain redirect. This page
    submits itself the moment it loads.

    The button is not decoration: if JavaScript is off, or the submit fires
    before the page has settled, the patient still has a way forward. A page
    that silently does nothing at the moment somebody is trying to pay is the
    worst possible failure here.
--}}

<x-layouts.app title="Taking you to payment" :hide-action-bar="true">
    <div class="mx-auto flex min-h-[60vh] max-w-md flex-col items-center justify-center gap-6 px-5 py-20 text-center">

        <span class="flex size-14 items-center justify-center rounded-full bg-brand-soft text-brand" aria-hidden="true">
            <svg class="size-7 animate-spin motion-reduce:animate-none" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-20" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" />
                <path class="opacity-90" fill="currentColor"
                      d="M4 12a8 8 0 018-8v3a5 5 0 00-5 5H4z" />
            </svg>
        </span>

        <div class="flex flex-col gap-2">
            <h1 class="text-2xl text-ink">Taking you to {{ $gatewayLabel }}</h1>
            <p class="leading-relaxed text-muted">
                Please wait a moment — do not close this window.
            </p>
        </div>

        <form id="gateway-handoff" method="POST" action="{{ $redirect->url }}">
            @foreach ($redirect->fields as $field => $value)
                <input type="hidden" name="{{ $field }}" value="{{ $value }}">
            @endforeach

            <x-ui.button type="submit" size="lg">Continue to payment</x-ui.button>
        </form>
    </div>

    @push('scripts')
        <script>
            document.getElementById('gateway-handoff')?.submit();
        </script>
    @endpush
</x-layouts.app>
