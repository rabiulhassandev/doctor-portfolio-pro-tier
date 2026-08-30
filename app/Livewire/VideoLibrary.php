<?php

namespace App\Livewire;

use App\Models\HealthVideo;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The patient education library: a filterable, searchable grid.
 *
 * Both the topic and the search term are bound to the query string, so a
 * filtered view can be shared, bookmarked and — importantly for a site whose
 * point is being found — linked to from elsewhere. It also means the back
 * button behaves the way people expect after they filter.
 */
class VideoLibrary extends Component
{
    use WithPagination;

    /** The topic filter. Empty string means "everything". */
    #[Url(as: 'topic', except: '')]
    public string $topic = '';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    /**
     * Both filters reset to page one.
     *
     * Without this, filtering while on page three shows an empty grid and looks
     * like the filter matched nothing.
     */
    public function updatedTopic(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['topic', 'search']);
        $this->resetPage();
    }

    /**
     * The topics that actually have a published video in them.
     *
     * @return Collection<int, string>
     */
    #[Computed]
    public function topics(): Collection
    {
        return HealthVideo::topics();
    }

    public function render()
    {
        return view('livewire.video-library', [
            'videos' => HealthVideo::query()
                ->published()
                ->inTopic($this->topic ?: null)
                ->search($this->search)
                ->ordered()
                ->paginate(config('site.videos_per_page', 12)),
        ]);
    }
}
