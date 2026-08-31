<x-layouts.app
    title="Articles"
    :description="'Articles about heart health and treatment, written by ' . $doctor->name . '.'">

    <x-ui.page-hero
        eyebrow="Reading"
        title="Articles"
        :lead="'Notes on the conditions ' . $doctor->shortName() . ' treats, written for patients rather than for colleagues.'" />

    <section class="paper-grain relative isolate bg-paper">
    <div class="mx-auto max-w-7xl px-5 py-14 sm:px-8 sm:py-20">
        @if ($posts->isEmpty())
            <x-ui.empty-state
                title="No articles yet"
                description="New writing will appear here."
                icon="M12 7.5h1.5m-1.5 3h1.5m-7.5 3h7.5m-7.5 3h7.5m3-9h3.375c.621 0 1.125.504 1.125 1.125V18a2.25 2.25 0 01-2.25 2.25M16.5 7.5V18a2.25 2.25 0 002.25 2.25M16.5 7.5V4.875c0-.621-.504-1.125-1.125-1.125H4.125C3.504 3.75 3 4.254 3 4.875V18a2.25 2.25 0 002.25 2.25h13.5" />
        @else
            {{-- Wider gaps than a card grid needs. With no card edges the only
                 thing separating one article from the next is white space, so
                 there has to be enough of it to do the job. --}}
            <div class="grid gap-10 sm:grid-cols-2 lg:grid-cols-3 lg:gap-x-8">
                @foreach ($posts as $post)
                    <x-ui.post-card :post="$post" data-reveal="{{ 50 * ($loop->index % 3) }}" />
                @endforeach
            </div>

            @if ($posts->hasPages())
                <div class="mt-12">{{ $posts->links() }}</div>
            @endif
        @endif
    </div>
    </section>
</x-layouts.app>
