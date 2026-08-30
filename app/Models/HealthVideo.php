<?php

namespace App\Models;

use App\Enums\VideoType;
use App\Support\Media;
use App\Support\VideoEmbed;
use Database\Factories\HealthVideoFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * A patient education video.
 *
 * The public site does not care where the video lives. Blade renders
 * `<x-ui.video-player :video="$video" />`, that component asks for
 * `$video->embedUrl()`, and this class works out what that means for an
 * uploaded MP4, a YouTube link or a Vimeo link.
 *
 * @property string $title
 * @property string $slug
 * @property string|null $description
 * @property string|null $topic
 * @property VideoType $video_type
 * @property string|null $source_url
 * @property string|null $video_id
 * @property string|null $video_hash
 * @property string|null $video_path
 * @property string|null $thumbnail_path
 * @property string|null $remote_thumbnail_url
 * @property int|null $duration_seconds
 * @property bool $is_featured
 * @property bool $is_published
 * @property Carbon|null $published_at
 * @property int $sort_order
 */
class HealthVideo extends Model
{
    /** @use HasFactory<HealthVideoFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'video_type' => VideoType::class,
            'is_featured' => 'boolean',
            'is_published' => 'boolean',
            'published_at' => 'datetime',
            'duration_seconds' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $video): void {
            if (blank($video->slug)) {
                $video->slug = Str::slug($video->title);
            }

            $video->normaliseSource();
        });
    }

    /**
     * Reduce whatever URL was pasted to a bare video id.
     *
     * Runs on every save — from the admin panel, a seeder, a test or tinker —
     * so no caller can forget it. This is pure normalisation with no side
     * effect, which is exactly the case a model hook is for. (Contrast with
     * appointment notifications, which are explicit service calls precisely
     * because they *do* have side effects. See AppointmentWorkflow.)
     *
     * The Vimeo thumbnail lookup below is the one exception, and it is
     * deliberately here rather than at render time: fetching it once when the
     * doctor saves costs them nothing, whereas fetching it per page view puts
     * a third-party HTTP call in the critical path of every visitor's page load.
     */
    protected function normaliseSource(): void
    {
        if ($this->video_type === VideoType::Upload) {
            $this->video_id = null;
            $this->video_hash = null;
            $this->remote_thumbnail_url = null;

            return;
        }

        if (blank($this->source_url)) {
            return;
        }

        $parsed = VideoEmbed::parse($this->source_url);

        if ($parsed === null) {
            return;
        }

        // Trust the URL over the dropdown: a doctor who picks "YouTube" and
        // pastes a Vimeo link means the link.
        $this->video_type = $parsed['type'];
        $this->video_id = $parsed['id'];
        $this->video_hash = $parsed['hash'];

        if ($this->video_type === VideoType::Vimeo && $this->isDirty(['source_url', 'video_id'])) {
            $this->remote_thumbnail_url = $this->fetchVimeoThumbnail();
        }
    }

    /**
     * Ask Vimeo for a thumbnail, once.
     *
     * Short timeout and a swallowed failure on purpose: Vimeo being slow or
     * down must not stop the doctor saving their video. Without a thumbnail
     * the card falls back to a branded placeholder, which is a far better
     * outcome than an error page.
     */
    protected function fetchVimeoThumbnail(): ?string
    {
        try {
            $response = Http::timeout(5)
                ->get('https://vimeo.com/api/oembed.json', [
                    'url' => 'https://vimeo.com/'.$this->video_id,
                ]);

            return $response->successful()
                ? ($response->json('thumbnail_url') ?: null)
                : null;
        } catch (Throwable $e) {
            Log::warning('Could not fetch the Vimeo thumbnail.', [
                'video_id' => $this->video_id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    // -----------------------------------------------------------------------
    // Scopes
    // -----------------------------------------------------------------------

    /** Live videos only: switched on, and the publish date passed. */
    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true)
            ->where(function (Builder $query): void {
                $query->whereNull('published_at')->orWhere('published_at', '<=', now());
            });
    }

    public function scopeFeatured(Builder $query): void
    {
        $query->where('is_featured', true);
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderByDesc('published_at')->orderByDesc('id');
    }

    public function scopeInTopic(Builder $query, ?string $topic): void
    {
        $query->when(filled($topic), fn (Builder $query) => $query->where('topic', $topic));
    }

    /** Free-text search across the title, description and topic. */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        $query->when($term !== '', function (Builder $query) use ($term): void {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

            $query->where(function (Builder $query) use ($like): void {
                $query->where('title', 'like', $like)
                    ->orWhere('description', 'like', $like)
                    ->orWhere('topic', 'like', $like);
            });
        });
    }

    /**
     * The distinct topics that actually have a published video in them.
     *
     * Drives the filter on the public library, so a topic never appears as a
     * filter that returns nothing.
     *
     * @return Collection<int, string>
     */
    public static function topics(): Collection
    {
        return static::query()
            ->published()
            ->whereNotNull('topic')
            ->where('topic', '!=', '')
            ->distinct()
            ->orderBy('topic')
            ->pluck('topic');
    }

    // -----------------------------------------------------------------------
    // Playback
    // -----------------------------------------------------------------------

    public function isEmbed(): bool
    {
        return $this->video_type->isEmbed();
    }

    /**
     * The single address the player component needs, whatever the source.
     *
     * For an embed it is the iframe src; for an upload it is the MP4 itself.
     * The component picks <iframe> or <video> from isEmbed() and asks for this
     * either way, so no view ever branches on the video type.
     */
    public function embedUrl(): ?string
    {
        return $this->isEmbed()
            ? VideoEmbed::embedUrl($this->video_type, $this->video_id, $this->video_hash)
            : Media::url($this->video_path);
    }

    /** The canonical page on YouTube or Vimeo, for SEO and the "watch on" link. */
    public function watchUrl(): ?string
    {
        return VideoEmbed::watchUrl($this->video_type, $this->video_id, $this->video_hash);
    }

    /**
     * Thumbnail, in order of preference:
     *
     *   1. One the doctor uploaded. Always wins — they chose it.
     *   2. YouTube's predictable address. No API key, no HTTP call.
     *   3. The Vimeo thumbnail cached at save time.
     *   4. Nothing, and the card draws a branded gradient instead. A
     *      placeholder built from the site's own colours looks deliberate;
     *      a broken image icon looks like the site is unmaintained.
     */
    public function thumbnailUrl(): ?string
    {
        if (filled($this->thumbnail_path)) {
            return Media::url($this->thumbnail_path);
        }

        return VideoEmbed::thumbnailUrl($this->video_type, $this->video_id)
            ?: $this->remote_thumbnail_url;
    }

    public function hasThumbnail(): bool
    {
        return filled($this->thumbnailUrl());
    }

    /** "4:32", or null when the doctor did not record a duration. */
    public function formattedDuration(): ?string
    {
        if (! $this->duration_seconds) {
            return null;
        }

        $minutes = intdiv($this->duration_seconds, 60);
        $seconds = $this->duration_seconds % 60;

        return $minutes >= 60
            ? sprintf('%d:%02d:%02d', intdiv($minutes, 60), $minutes % 60, $seconds)
            : sprintf('%d:%02d', $minutes, $seconds);
    }

    /** ISO 8601 duration, which is what schema.org VideoObject expects. */
    public function iso8601Duration(): ?string
    {
        if (! $this->duration_seconds) {
            return null;
        }

        return sprintf(
            'PT%dM%dS',
            intdiv($this->duration_seconds, 60),
            $this->duration_seconds % 60,
        );
    }

    public function summary(int $limit = 140): string
    {
        return Str::limit(strip_tags((string) $this->description), $limit);
    }

    /**
     * Other published videos on the same topic.
     *
     * @return Collection<int, self>
     */
    public function relatedVideos(int $limit = 3): Collection
    {
        if (blank($this->topic)) {
            return collect();
        }

        return static::query()
            ->published()
            ->where('topic', $this->topic)
            ->whereKeyNot($this->getKey())
            ->ordered()
            ->limit($limit)
            ->get();
    }
}
