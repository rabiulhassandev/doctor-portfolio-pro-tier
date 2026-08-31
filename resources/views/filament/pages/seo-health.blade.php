{{--
    The search health check.

    A ranked list of specific problems, each with a link to the screen that
    fixes it. Deliberately NOT a score out of a hundred — see the note at the
    top of App\Support\SeoAudit for why.

    Styled with Filament's own tokens rather than the public site's palette:
    this is a working screen inside the admin panel and should look like the
    rest of it.
--}}

@php
    $findings = $this->getFindings();
    $summary = $this->getSummary();

    $tones = [
        \App\Support\SeoAudit::CRITICAL => [
            'label' => 'Needs fixing now',
            'ring' => 'ring-danger-300 dark:ring-danger-500/40',
            'bg' => 'bg-danger-50 dark:bg-danger-500/10',
            'text' => 'text-danger-700 dark:text-danger-300',
            'icon' => 'heroicon-o-exclamation-triangle',
        ],
        \App\Support\SeoAudit::WARNING => [
            'label' => 'Worth fixing',
            'ring' => 'ring-warning-300 dark:ring-warning-500/40',
            'bg' => 'bg-warning-50 dark:bg-warning-500/10',
            'text' => 'text-warning-700 dark:text-warning-300',
            'icon' => 'heroicon-o-exclamation-circle',
        ],
        \App\Support\SeoAudit::SUGGESTION => [
            'label' => 'Could be better',
            'ring' => 'ring-gray-200 dark:ring-white/10',
            'bg' => 'bg-gray-50 dark:bg-white/5',
            'text' => 'text-gray-600 dark:text-gray-400',
            'icon' => 'heroicon-o-light-bulb',
        ],
    ];
@endphp

<x-filament-panels::page>

    {{-- The tally. Three numbers, so the scale of the job is clear before
         reading a word of it. --}}
    <div class="grid gap-4 sm:grid-cols-3">
        @foreach ($tones as $severity => $tone)
            @php $count = $summary[$severity] ?? 0; @endphp

            <div class="rounded-xl p-4 ring-1 {{ $count > 0 ? $tone['ring'] : 'ring-gray-200 dark:ring-white/10' }} {{ $count > 0 ? $tone['bg'] : 'bg-white dark:bg-gray-900' }}">
                <div class="flex items-center gap-3">
                    <x-filament::icon
                        :icon="$tone['icon']"
                        @class([
                            'h-6 w-6',
                            $tone['text'] => $count > 0,
                            'text-gray-300 dark:text-gray-600' => $count === 0,
                        ]) />

                    <div class="flex flex-col">
                        <span @class([
                            'text-2xl font-bold leading-none tabular-nums',
                            $tone['text'] => $count > 0,
                            'text-gray-400 dark:text-gray-600' => $count === 0,
                        ])>{{ $count }}</span>
                        <span class="text-sm text-gray-500 dark:text-gray-400">{{ $tone['label'] }}</span>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    @if ($findings->isEmpty())
        {{-- The state this screen exists to reach. Worth making it feel like an
             arrival rather than an empty table. --}}
        <div class="flex flex-col items-center gap-3 rounded-xl bg-white px-6 py-16 text-center ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
            <x-filament::icon icon="heroicon-o-check-badge" class="h-12 w-12 text-success-500" />
            <h3 class="text-lg font-semibold text-gray-950 dark:text-white">Nothing to fix</h3>
            <p class="max-w-md text-sm text-gray-500 dark:text-gray-400">
                Every check passed. Keep publishing — new articles, videos and answered questions are
                what keep a site being found.
            </p>
        </div>
    @else
        <div class="flex flex-col gap-3">
            @foreach ($findings as $finding)
                @php $tone = $tones[$finding['severity']]; @endphp

                <div class="flex flex-col gap-3 rounded-xl bg-white p-5 ring-1 {{ $tone['ring'] }} dark:bg-gray-900 sm:flex-row sm:items-start sm:gap-4">
                    <x-filament::icon
                        :icon="$tone['icon']"
                        class="h-5 w-5 shrink-0 sm:mt-0.5 {{ $tone['text'] }}" />

                    <div class="flex min-w-0 flex-1 flex-col gap-1">
                        <h3 class="font-semibold text-gray-950 dark:text-white">
                            {{ $finding['title'] }}
                        </h3>
                        <p class="text-sm leading-relaxed text-gray-500 dark:text-gray-400">
                            {{ $finding['detail'] }}
                        </p>
                    </div>

                    @if ($finding['url'])
                        <x-filament::button
                            :href="$finding['url']"
                            tag="a"
                            size="sm"
                            color="gray"
                            class="shrink-0">
                            {{ $finding['action'] ?? 'Fix' }}
                        </x-filament::button>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    {{-- The two things nobody thinks to do, and the two that matter most on a
         new site. Shown always, because they are not problems to be fixed and
         would never appear in the list above. --}}
    <div class="rounded-xl bg-gray-50 p-5 ring-1 ring-gray-200 dark:bg-white/5 dark:ring-white/10">
        <h3 class="mb-2 font-semibold text-gray-950 dark:text-white">Two things worth doing once</h3>
        <ol class="flex list-decimal flex-col gap-2 pl-5 text-sm leading-relaxed text-gray-500 dark:text-gray-400">
            <li>
                Add this site to
                <a href="https://search.google.com/search-console" target="_blank" rel="noopener noreferrer"
                   class="font-medium text-primary-600 hover:underline dark:text-primary-400">Google Search Console</a>,
                paste the verification code into the SEO settings, and submit
                <code class="rounded bg-gray-200 px-1 py-0.5 text-xs dark:bg-white/10">{{ route('sitemap') }}</code>.
                It is free and it is the only place that tells you what people searched for before they found you.
            </li>
            <li>
                Create a
                <a href="https://business.google.com" target="_blank" rel="noopener noreferrer"
                   class="font-medium text-primary-600 hover:underline dark:text-primary-400">Google Business Profile</a>
                for the chamber. For a practice with one address, that listing brings more patients than
                everything on this website put together — and nothing in an admin panel can do it for you.
            </li>
        </ol>
    </div>
</x-filament-panels::page>
