<?php

namespace App\Support;

use App\Enums\VideoType;
use App\Models\HealthVideo;

/**
 * Understands the video URLs a doctor might paste.
 *
 * Nobody copies a canonical embed URL. They copy whatever was in the address
 * bar, or whatever the Share button gave them, and that is a different shape on
 * every platform and on mobile. This class accepts all of them and reduces each
 * to a bare id, which is what actually gets stored.
 *
 * Pure static functions: no database, no HTTP, no clock. That makes it trivial
 * to test against a list of real-world URLs, which is the only way to have any
 * confidence in code like this.
 *
 * @see HealthVideo
 */
final class VideoEmbed
{
    /**
     * Every YouTube URL shape in one expression, plus a bare-id fallback.
     *
     * Covers: watch?v=, youtu.be/, /embed/, /shorts/, /live/, /v/, the
     * privacy-preserving youtube-nocookie.com host, and any of them carrying
     * extra query parameters or a ?t= timestamp.
     *
     * A YouTube id is always exactly 11 characters from a URL-safe alphabet,
     * which is what makes the match reliable rather than merely hopeful.
     */
    private const YOUTUBE_PATTERN = '~(?:youtu\.be/|youtube(?:-nocookie)?\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/|v/))([A-Za-z0-9_-]{11})~i';

    /**
     * Vimeo ids are numeric. The optional second group is the unlisted hash —
     * see the note on embedUrl() for why it has to be carried through.
     */
    private const VIMEO_PATTERN = '~vimeo\.com/(?:channels/[\w]+/|groups/[\w]+/videos/|video/)?(\d{6,})(?:/([A-Za-z0-9]+))?~i';

    /**
     * Work out what a pasted URL points at.
     *
     * @return array{type: VideoType, id: string, hash: string|null}|null
     *                                                                    Null when the string is not a video URL we can use — which the
     *                                                                    admin form turns into a validation error at paste time, rather
     *                                                                    than letting the doctor discover a blank player a week later.
     */
    public static function parse(?string $url): ?array
    {
        if (blank($url)) {
            return null;
        }

        $url = trim($url);

        if ($id = self::youtubeId($url)) {
            return ['type' => VideoType::Youtube, 'id' => $id, 'hash' => null];
        }

        if ($vimeo = self::vimeo($url)) {
            return ['type' => VideoType::Vimeo, 'id' => $vimeo['id'], 'hash' => $vimeo['hash']];
        }

        return null;
    }

    public static function youtubeId(string $url): ?string
    {
        if (preg_match(self::YOUTUBE_PATTERN, $url, $matches) === 1) {
            return $matches[1];
        }

        // Someone pasted just the id. Accept it — it is unambiguous.
        if (preg_match('~^[A-Za-z0-9_-]{11}$~', trim($url)) === 1) {
            return trim($url);
        }

        return null;
    }

    /**
     * @return array{id: string, hash: string|null}|null
     */
    public static function vimeo(string $url): ?array
    {
        // player.vimeo.com/video/ID is the embed form, and the hash arrives as
        // a ?h= parameter there rather than as a path segment.
        if (preg_match('~player\.vimeo\.com/video/(\d{6,})~i', $url, $matches) === 1) {
            parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

            return ['id' => $matches[1], 'hash' => $query['h'] ?? null];
        }

        if (preg_match(self::VIMEO_PATTERN, $url, $matches) === 1) {
            return ['id' => $matches[1], 'hash' => $matches[2] ?? null];
        }

        return null;
    }

    /**
     * The address to put in an <iframe src>.
     *
     * YouTube goes through youtube-nocookie.com, which does not set tracking
     * cookies until the visitor actually presses play. On a page where the
     * topic is somebody's illness that is the right default, and it is also
     * what keeps European buyers out of consent-banner territory.
     *
     * `rel=0` keeps the end-of-video suggestions within the same channel
     * instead of offering whatever YouTube feels like next — which on a medical
     * page can be actively harmful.
     *
     * The Vimeo hash is not optional for unlisted videos. Without it the player
     * returns "private video", which looks exactly like a broken embed and is
     * miserable to diagnose.
     */
    public static function embedUrl(VideoType $type, ?string $id, ?string $hash = null): ?string
    {
        if (blank($id)) {
            return null;
        }

        return match ($type) {
            VideoType::Youtube => "https://www.youtube-nocookie.com/embed/{$id}?rel=0&modestbranding=1",
            VideoType::Vimeo => 'https://player.vimeo.com/video/'.$id.(filled($hash) ? "?h={$hash}" : ''),
            VideoType::Upload => null,
        };
    }

    /** The canonical public page, used for the "watch on YouTube" link and SEO. */
    public static function watchUrl(VideoType $type, ?string $id, ?string $hash = null): ?string
    {
        if (blank($id)) {
            return null;
        }

        return match ($type) {
            VideoType::Youtube => "https://www.youtube.com/watch?v={$id}",
            VideoType::Vimeo => 'https://vimeo.com/'.$id.(filled($hash) ? "/{$hash}" : ''),
            VideoType::Upload => null,
        };
    }

    /**
     * A thumbnail that can be derived without an API call.
     *
     * YouTube's image addresses are predictable, so no key and no HTTP request
     * are needed. `hqdefault` rather than `maxresdefault`: the latter only
     * exists for videos uploaded above a certain resolution and 404s on a large
     * share of real ones, leaving a broken image in the grid.
     *
     * Vimeo publishes no such scheme — its thumbnails have to be looked up
     * through oEmbed, which HealthVideo does once at save time and caches.
     */
    public static function thumbnailUrl(VideoType $type, ?string $id): ?string
    {
        if (blank($id)) {
            return null;
        }

        return match ($type) {
            VideoType::Youtube => "https://i.ytimg.com/vi/{$id}/hqdefault.jpg",
            VideoType::Vimeo, VideoType::Upload => null,
        };
    }
}
