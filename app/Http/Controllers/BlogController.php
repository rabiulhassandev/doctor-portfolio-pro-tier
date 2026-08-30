<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class BlogController extends Controller
{
    public function index(): View
    {
        $this->ensureEnabled();

        return view('pages.blog.index', [
            'posts' => BlogPost::query()
                ->published()
                ->latestFirst()
                ->paginate(config('site.blog_per_page', 6)),
        ]);
    }

    public function show(BlogPost $post): View
    {
        $this->ensureEnabled();

        /*
         | Route-model binding resolves the slug without knowing about drafts,
         | so a draft or a scheduled article would otherwise be readable by
         | anyone who guessed its address.
         */
        abort_unless(BlogPost::query()->published()->whereKey($post->getKey())->exists(), 404);

        return view('pages.blog.show', [
            'post' => $post,
            'related' => BlogPost::query()
                ->published()
                ->whereKeyNot($post->getKey())
                ->latestFirst()
                ->limit(3)
                ->get(),
        ]);
    }

    private function ensureEnabled(): void
    {
        if (! config('site.features.blog')) {
            throw new NotFoundHttpException;
        }
    }
}
