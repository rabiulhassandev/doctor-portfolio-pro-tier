<?php

namespace App\Enums;

use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * Where a health video's file actually lives.
 *
 * The public site does not branch on this. Blade calls
 * `<x-ui.video-player :video="$video" />` and the component asks the model for
 * `$video->embed_url`; the model works out what that means. This enum exists so
 * the *model* and the *admin form* know which fields matter.
 *
 * Uploading is supported because some doctors record short clips on a phone and
 * have nowhere to put them, but YouTube or Vimeo is the recommended route and
 * the admin form says so: a self-hosted 200 MB MP4 on shared hosting will be
 * the slowest thing on the site by a wide margin.
 */
enum VideoType: string implements HasIcon, HasLabel
{
    /** An MP4 the doctor uploaded, served from this site's own storage. */
    case Upload = 'upload';

    case Youtube = 'youtube';

    case Vimeo = 'vimeo';

    public function getLabel(): string
    {
        return match ($this) {
            self::Upload => 'Upload a video file',
            self::Youtube => 'YouTube link',
            self::Vimeo => 'Vimeo link',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Upload => 'heroicon-o-arrow-up-tray',
            self::Youtube, self::Vimeo => 'heroicon-o-link',
        };
    }

    /** True when the video plays inside an <iframe> from someone else's server. */
    public function isEmbed(): bool
    {
        return $this !== self::Upload;
    }
}
