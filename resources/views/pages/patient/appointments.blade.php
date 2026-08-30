<x-layouts.app title="My appointments">
    <x-patient.shell
        title="My appointments"
        subtitle="Everything you have booked with the chamber.">

        <div class="flex flex-col gap-10">

            <section class="flex flex-col gap-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-xl text-ink">Coming up</h2>

                    @if (config('site.features.booking'))
                        <x-ui.button :href="route('booking')" size="sm">Book another</x-ui.button>
                    @endif
                </div>

                @forelse ($upcoming as $appointment)
                    <x-patient.appointment-card :appointment="$appointment" />
                @empty
                    <x-ui.empty-state
                        title="Nothing coming up"
                        description="When you book an appointment it will appear here.">
                        @if (config('site.features.booking'))
                            <x-ui.button :href="route('booking')">Book an appointment</x-ui.button>
                        @endif
                    </x-ui.empty-state>
                @endforelse
            </section>

            @if ($past->isNotEmpty())
                <section class="flex flex-col gap-4">
                    <h2 class="text-xl text-ink">Past appointments</h2>

                    @foreach ($past as $appointment)
                        <x-patient.appointment-card :appointment="$appointment" />
                    @endforeach

                    @if ($past->hasPages())
                        <div class="pt-2">{{ $past->links() }}</div>
                    @endif
                </section>
            @endif
        </div>
    </x-patient.shell>
</x-layouts.app>
