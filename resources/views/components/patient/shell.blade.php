{{--
    The frame around every signed-in patient page.

    A tabbed sub-navigation rather than a sidebar. A patient has four screens in
    total, and a dashboard chrome heavy enough for an admin panel would make a
    consumer site feel like paperwork.

        <x-patient.shell title="My appointments">
            …
        </x-patient.shell>
--}}

@props([
    'title',
    'subtitle' => null,
])

@php
    $patient = auth('patient')->user();

    $tabs = [
        ['label' => 'Overview', 'route' => 'patient.dashboard', 'icon' => 'M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75'],
        ['label' => 'Appointments', 'route' => 'patient.appointments.index', 'icon' => 'M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5'],
        ['label' => 'Documents', 'route' => 'patient.documents.index', 'icon' => 'M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z'],
        ['label' => 'My details', 'route' => 'patient.profile', 'icon' => 'M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z'],
    ];
@endphp

{{-- The dark header band. Every page on this site opens dark — see the note
     at the top of components/site/navbar.blade.php for why that is a rule. --}}
<div class="surface-grain relative isolate overflow-hidden bg-night">
    <div class="pointer-events-none absolute inset-0 -z-10"
         style="background: radial-gradient(36rem 20rem at 85% 120%, color-mix(in oklab, var(--brand-brass) 14%, transparent), transparent 70%);"
         aria-hidden="true"></div>

    <div class="mx-auto max-w-6xl px-5 pt-32 sm:px-8 sm:pt-36">

        {{-- Greeting --}}
        <div class="flex flex-wrap items-start justify-between gap-4" data-reveal>
            <div class="flex items-center gap-4">
                <span class="flex size-12 shrink-0 items-center justify-center border border-brass/45 font-display text-lg leading-none text-brass"
                      aria-hidden="true">
                    {{ $patient->initials() }}
                </span>

                <div class="flex flex-col gap-0.5">
                    <h1 class="text-3xl text-white sm:text-4xl">{{ $title }}</h1>
                    @if ($subtitle)
                        <p class="text-[0.9375rem] text-white/55">{{ $subtitle }}</p>
                    @endif
                </div>
            </div>

            <form method="POST" action="{{ route('patient.logout') }}">
                @csrf
                <button type="submit"
                        class="link-underline text-[0.8125rem] font-medium uppercase tracking-[0.13em] text-white/55 transition-colors hover:text-white">
                    Sign out
                </button>
            </form>
        </div>

        {{-- Tabs. Scroll sideways on a phone rather than wrapping onto two rows. --}}
        <nav class="scrollbar-none -mb-px mt-10 flex gap-1 overflow-x-auto" aria-label="Your account">
            @foreach ($tabs as $tab)
                @php $active = request()->routeIs($tab['route']); @endphp

                <a href="{{ route($tab['route']) }}"
                   @if ($active) aria-current="page" @endif
                   @class([
                       'flex shrink-0 items-center gap-2 border-b-2 px-4 py-3.5 text-[0.8125rem] font-semibold uppercase tracking-[0.11em] transition-colors',
                       'border-brass text-brass' => $active,
                       'border-transparent text-white/50 hover:border-white/25 hover:text-white' => ! $active,
                   ])>
                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $tab['icon'] }}" />
                    </svg>
                    {{ $tab['label'] }}
                </a>
            @endforeach
        </nav>
    </div>
</div>

<div class="mx-auto max-w-6xl px-5 py-10 sm:px-8 sm:py-12">
    @if (session('status'))
        <x-ui.alert tone="positive" class="mb-6">{{ session('status') }}</x-ui.alert>
    @endif

    @if ($errors->any() && ! $errors->has('appointment'))
        <x-ui.alert tone="negative" title="Please check the form" class="mb-6">
            {{ $errors->first() }}
        </x-ui.alert>
    @endif

    @error('appointment')
        <x-ui.alert tone="caution" class="mb-6">{{ $message }}</x-ui.alert>
    @enderror

    {{ $slot }}
</div>
