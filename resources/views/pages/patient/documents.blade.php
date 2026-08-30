<x-layouts.app title="My documents">
    <x-patient.shell
        title="My documents"
        subtitle="Prescriptions and reports issued by the chamber.">

        @if ($documents->isEmpty())
            <x-ui.empty-state
                title="Nothing here yet"
                description="Prescriptions and reports appear here as soon as the doctor issues them. You will get an email when one is ready."
                icon="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
        @else
            <div class="flex flex-col gap-3">
                @foreach ($documents as $document)
                    <x-ui.card padding="tight" class="group">
                        <div class="flex flex-wrap items-center gap-4">
                            <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-brand-soft text-brand" aria-hidden="true">
                                <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ match ($document->kind->value) {
                                        'prescription' => 'M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586',
                                        'report' => 'M9.75 3.104v5.714a2.25 2.25 0 01-.659 1.591L5 14.5M9.75 3.104c-.251.023-.501.05-.75.082m.75-.082a24.301 24.301 0 014.5 0m0 0v5.714c0 .597.237 1.17.659 1.591L19.8 15.3M14.25 3.104c.251.023.501.05.75.082M19.8 15.3l-1.57.393A9.065 9.065 0 0112 15a9.065 9.065 0 00-6.23-.693L5 14.5m14.8.8l1.402 1.402c1.232 1.232.65 3.318-1.067 3.611A48.309 48.309 0 0112 21c-2.773 0-5.491-.235-8.135-.687-1.718-.293-2.3-2.379-1.067-3.61L5 14.5',
                                        'invoice' => 'M9 14.25l6-6m4.5-3.493V21.75l-3.75-1.5-3.75 1.5-3.75-1.5-3.75 1.5V4.757c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0111.186 0c1.1.128 1.907 1.077 1.907 2.185zM9.75 9h.008v.008H9.75V9zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm4.125 4.5h.008v.008h-.008V13.5zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z',
                                        default => 'M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z',
                                    } }}" />
                                </svg>
                            </span>

                            <div class="flex min-w-0 flex-1 flex-col gap-1">
                                <p class="font-semibold text-ink">{{ $document->title }}</p>

                                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-muted">
                                    <x-ui.badge tone="neutral">{{ $document->kind->getLabel() }}</x-ui.badge>
                                    <span>{{ $document->created_at->format('j F Y') }}</span>
                                    <span>{{ $document->formattedSize() }}</span>

                                    @if ($document->appointment)
                                        <span>From your visit on {{ $document->appointment->startsAtLocal()->format('j M Y') }}</span>
                                    @endif
                                </div>
                            </div>

                            <x-ui.button :href="route('documents.download', $document)" variant="secondary" size="sm">
                                Download
                            </x-ui.button>
                        </div>
                    </x-ui.card>
                @endforeach

                @if ($documents->hasPages())
                    <div class="pt-2">{{ $documents->links() }}</div>
                @endif
            </div>
        @endif
    </x-patient.shell>
</x-layouts.app>
