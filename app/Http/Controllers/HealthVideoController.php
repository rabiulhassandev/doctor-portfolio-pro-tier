<?php

namespace App\Http\Controllers;

use App\Models\HealthVideo;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The patient education library.
 *
 * The grid itself is a Livewire component (App\Livewire\VideoLibrary) so the
 * topic filter and the search box work without a page reload. This controller
 * only renders the page around it.
 */
class HealthVideoController extends Controller
{
    public function index(): View
    {
        $this->ensureEnabled();

        return view('pages.videos.index');
    }

    public function show(HealthVideo $video): View
    {
        $this->ensureEnabled();

        // As with articles: binding on the slug knows nothing about drafts.
        abort_unless(HealthVideo::query()->published()->whereKey($video->getKey())->exists(), 404);

        return view('pages.videos.show', [
            'video' => $video,
            'related' => $video->relatedVideos(),
        ]);
    }

    private function ensureEnabled(): void
    {
        if (! config('site.features.health_videos')) {
            throw new NotFoundHttpException;
        }
    }
}
