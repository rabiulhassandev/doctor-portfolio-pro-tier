<?php

use App\Enums\VideoType;
use App\Support\VideoEmbed;

/*
|--------------------------------------------------------------------------
| Video URL parsing
|--------------------------------------------------------------------------
|
| Nobody copies a canonical embed URL. They copy whatever was in the address
| bar, or whatever the Share button gave them — and that is a different shape
| on desktop, on mobile, and on every platform.
|
| The dataset below is the actual point of this file: the only way to have any
| confidence in code like this is to throw real-world URLs at it. VideoEmbed is
| pure static functions with no database and no clock, which is what makes that
| cheap to do.
|
*/

describe('YouTube', function () {
    it('extracts the id from every shape a doctor might paste', function (string $url) {
        expect(VideoEmbed::youtubeId($url))->toBe('dQw4w9WgXcQ');
    })->with([
        'watch page' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'without www' => 'https://youtube.com/watch?v=dQw4w9WgXcQ',
        'http' => 'http://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'share link' => 'https://youtu.be/dQw4w9WgXcQ',
        'share link with timestamp' => 'https://youtu.be/dQw4w9WgXcQ?t=42',
        'embed url' => 'https://www.youtube.com/embed/dQw4w9WgXcQ',
        'no-cookie embed' => 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ',
        'shorts' => 'https://www.youtube.com/shorts/dQw4w9WgXcQ',
        'live' => 'https://www.youtube.com/live/dQw4w9WgXcQ',
        'old /v/ form' => 'https://www.youtube.com/v/dQw4w9WgXcQ',
        'extra params before v' => 'https://www.youtube.com/watch?feature=share&v=dQw4w9WgXcQ',
        'extra params after v' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ&list=PL123&index=2',
        'timestamp on watch page' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=90s',
        'bare id' => 'dQw4w9WgXcQ',
        'with surrounding spaces' => '  https://youtu.be/dQw4w9WgXcQ  ',
    ]);

    it('reports the type and builds a privacy-preserving embed', function () {
        $parsed = VideoEmbed::parse('https://www.youtube.com/watch?v=dQw4w9WgXcQ');

        expect($parsed['type'])->toBe(VideoType::Youtube)
            ->and($parsed['id'])->toBe('dQw4w9WgXcQ')
            ->and($parsed['hash'])->toBeNull();

        // youtube-nocookie sets no tracking cookie until the visitor presses
        // play — the right default on a page about somebody's illness.
        expect(VideoEmbed::embedUrl(VideoType::Youtube, 'dQw4w9WgXcQ'))
            ->toContain('youtube-nocookie.com/embed/dQw4w9WgXcQ')
            // rel=0 keeps the end screen inside the doctor's own channel rather
            // than offering whatever YouTube feels like next.
            ->toContain('rel=0');
    });

    it('uses hqdefault for thumbnails', function () {
        // maxresdefault only exists above a certain upload resolution and 404s
        // on a large share of real videos, leaving a broken image in the grid.
        expect(VideoEmbed::thumbnailUrl(VideoType::Youtube, 'dQw4w9WgXcQ'))
            ->toBe('https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg');
    });
});

describe('Vimeo', function () {
    it('extracts the id from every shape', function (string $url) {
        expect(VideoEmbed::vimeo($url)['id'])->toBe('76979871');
    })->with([
        'plain' => 'https://vimeo.com/76979871',
        'without www' => 'http://vimeo.com/76979871',
        'player' => 'https://player.vimeo.com/video/76979871',
        'channel' => 'https://vimeo.com/channels/staffpicks/76979871',
        'group' => 'https://vimeo.com/groups/shortfilms/videos/76979871',
    ]);

    it('carries the unlisted hash through, because the embed breaks without it', function () {
        // Miss this and an unlisted video renders as "private", which looks
        // exactly like a broken embed and is miserable to diagnose.
        $fromPath = VideoEmbed::parse('https://vimeo.com/76979871/abcdef1234');

        expect($fromPath['type'])->toBe(VideoType::Vimeo)
            ->and($fromPath['hash'])->toBe('abcdef1234')
            ->and(VideoEmbed::embedUrl(VideoType::Vimeo, '76979871', 'abcdef1234'))
            ->toBe('https://player.vimeo.com/video/76979871?h=abcdef1234');

        // The player URL carries it as a query parameter instead.
        $fromQuery = VideoEmbed::vimeo('https://player.vimeo.com/video/76979871?h=abcdef1234');

        expect($fromQuery['hash'])->toBe('abcdef1234');
    });

    it('has no derivable thumbnail, which is why the model caches one', function () {
        expect(VideoEmbed::thumbnailUrl(VideoType::Vimeo, '76979871'))->toBeNull();
    });
});

describe('rejecting what it cannot use', function () {
    it('returns null rather than guessing', function (?string $url) {
        expect(VideoEmbed::parse($url))->toBeNull();
    })->with([
        'empty' => '',
        'null' => null,
        'not a url' => 'just some words',
        'another site' => 'https://example.com/video.mp4',
        'youtube channel, not a video' => 'https://www.youtube.com/@somechannel',
        'vimeo profile, not a video' => 'https://vimeo.com/someuser',
        'id too short' => 'abc123',
    ]);

    it('produces no embed url without an id', function () {
        expect(VideoEmbed::embedUrl(VideoType::Youtube, null))->toBeNull()
            // An uploaded file has no embed URL by definition — the model
            // returns the file itself instead.
            ->and(VideoEmbed::embedUrl(VideoType::Upload, 'anything'))->toBeNull();
    });
});
