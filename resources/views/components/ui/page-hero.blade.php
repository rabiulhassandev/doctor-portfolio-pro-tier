{{--
    The banner at the top of every page except the home page.

        <x-ui.page-hero
            eyebrow="Patient education"
            title="Health videos"
            lead="Short films about the conditions I treat." />

    ---------------------------------------------------------------------------
    THE PHOTOGRAPH
    ---------------------------------------------------------------------------

    A full-bleed picture under a dark overlay. The page does NOT name the file:
    Media::banner() looks the current route up in config/site.php's `banners`
    map, so choosing the photography for the whole site stays one edit in the
    rebrand file. Pass `:image="..."` only to override a single page.

    If there is no photograph — a fresh install before seeding, or a buyer who
    emptied the map — the band renders as a plain dark panel and still looks
    finished. The picture is an enhancement, never a dependency.

    ---------------------------------------------------------------------------
    WHY THE OVERLAY IS TWO LAYERS AND NOT ONE
    ---------------------------------------------------------------------------

    A single flat scrim at the strength that makes white type legible over the
    brightest part of the picture makes the whole image mud. Instead:

      * a horizontal gradient, heaviest on the side the words sit on, so the
        photograph survives on the other side;
      * a vertical one that goes nearly solid at the bottom edge, which is what
        hands the page off to the section below instead of stopping dead.

    Between them the type sits on near-black wherever it actually is, and the
    picture stays a picture everywhere else.

    Still a BAND, not a second hero: no call to action, and short enough that
    the page's real content is on screen. Interior pages exist to be read.
--}}

@props([
    'eyebrow' => null,
    'title' => '',
    'lead' => null,
    /*
     | Overrides the config map for this one page. Accepts a path on the public
     | disk or an absolute URL. Pass `false` to force the plain dark band.
     */
    'image' => null,
    // Looks up a different key in the banners map — for screens that are not a
    // route of their own, such as the patient sign-in shell.
    'bannerKey' => null,
    /*
     | Must match the container the PAGE BELOW uses.
     |
     | The photograph is always full-bleed; this only sets where the heading
     | starts. Get it wrong and the banner's left edge and the content's left
     | edge disagree by a couple of hundred pixels, which reads as two grids
     | bolted together rather than as one page.
     |
     | wide (max-w-7xl) · medium (max-w-6xl) · narrow (max-w-3xl)
     */
    'width' => 'wide',
])

@php
    use App\Support\Media;

    $photo = $image === false ? null : Media::banner($image, $bannerKey);

    $measure = [
        'wide' => 'max-w-7xl',
        'medium' => 'max-w-6xl',
        'narrow' => 'max-w-3xl',
    ][$width] ?? 'max-w-7xl';
@endphp

<header class="surface-grain relative isolate flex min-h-[19rem] items-end overflow-hidden bg-night sm:min-h-[23rem]">

    @if ($photo)
        {{-- Slightly desaturated and darkened in CSS rather than in the file, so
             a buyer can drop in any photograph without editing it first. --}}
        <img src="{{ $photo }}" alt="" aria-hidden="true"
             class="absolute inset-0 -z-20 size-full object-cover opacity-80 [filter:saturate(0.8)_brightness(0.95)]">

        {{-- Horizontal: heaviest under the words. --}}
        <div class="absolute inset-0 -z-10 bg-gradient-to-r from-night via-night/75 to-night/20" aria-hidden="true"></div>
    @else
        {{-- No photograph. The bloom that used to carry this band on its own. --}}
        <div class="pointer-events-none absolute inset-0 -z-10"
             style="background: radial-gradient(38rem 22rem at 82% 120%, color-mix(in oklab, var(--brand-brass) 16%, transparent), transparent 70%);"
             aria-hidden="true"></div>
    @endif

    {{-- Vertical: dark enough at the top for the navbar to sit on, dark again
         at the bottom for the heading. It does NOT go solid at the foot — the
         section below is paper, so there is nothing down there to blend into
         and a solid bottom edge only throws away the lower half of the
         photograph. The brass hairline makes the join. --}}
    <div class="absolute inset-0 -z-10 bg-gradient-to-b from-night/85 via-night/25 to-night/60" aria-hidden="true"></div>

    {{-- The brass hairline along the bottom edge: the one detail that makes the
         band read as deliberate rather than as a dark rectangle. --}}
    <div class="pointer-events-none absolute inset-x-0 bottom-0 h-px bg-gradient-to-r from-transparent via-brass/50 to-transparent"
         aria-hidden="true"></div>

    <div class="mx-auto w-full {{ $measure }} px-5 pb-10 pt-28 sm:px-8 sm:pb-14 sm:pt-36">
        <div class="flex max-w-3xl flex-col gap-4 sm:gap-5" data-reveal>
            @if ($eyebrow)
                <div class="flex items-center gap-3">
                    <span class="rule-brass"></span>
                    <p class="eyebrow eyebrow-light">{{ $eyebrow }}</p>
                </div>
            @endif

            <h1 class="text-[2.25rem] leading-[1.05] text-white sm:text-5xl lg:text-6xl">{{ $title }}</h1>

            @if ($lead)
                <p class="max-w-[38rem] text-base leading-relaxed text-white/70 sm:text-lg">{{ $lead }}</p>
            @endif

            {{ $slot }}
        </div>
    </div>
</header>
