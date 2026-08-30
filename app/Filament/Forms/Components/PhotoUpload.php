<?php

namespace App\Filament\Forms\Components;

use Filament\Forms\Components\FileUpload;

/**
 * The upload field used for every picture on the website.
 *
 * All the image fields in the panel — the doctor's portrait, blog covers,
 * gallery photos, video thumbnails, testimonial headshots — want the same
 * behaviour, so it is configured once here instead of seven times:
 *
 *     PhotoUpload::make('cover_image')
 *         ->label('Cover image')
 *         ->directory('blog')
 *         ->guidance('Wide images work best.')
 *
 * The accepted file list is the part that matters. Filament's `->image()`
 * allows anything matching `image/*`, and that includes SVG — the one image
 * format the panel cannot actually work with. The field builds its thumbnail by
 * drawing the file into a canvas and hangs the crop/rotate editor off that
 * thumbnail, so a vector file leaves the doctor looking at a blank grey panel
 * with no preview and no way to edit the picture. Naming the three formats a
 * camera or a phone actually produces avoids that, and anyone who tries an SVG
 * is told so at upload time instead of discovering it later.
 */
class PhotoUpload extends FileUpload
{
    /** Formats the browser can preview and the built-in editor can crop. */
    public const ACCEPTED_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->image()
            ->acceptedFileTypes(self::ACCEPTED_TYPES)
            ->imageEditor()
            ->maxSize(4096)
            // Both open the picture in a new tab rather than replacing it,
            // which is usually what someone reaching for them wants.
            ->openable()
            ->downloadable();
    }

    /**
     * Filament's avatar preset calls image() again, which puts `image/*` back
     * and lets SVGs through the front door a second time. Close it behind it.
     */
    public function avatar(): static
    {
        parent::avatar();

        return $this->acceptedFileTypes(self::ACCEPTED_TYPES);
    }

    /**
     * Set the helper line, with the accepted formats and size limit appended.
     *
     * Written as a closure so the limit is read after any ->maxSize() the
     * caller adds, whichever order the two are chained in.
     */
    public function guidance(string $guidance = ''): static
    {
        return $this->helperText(fn (): string => trim(sprintf(
            '%s JPG, PNG or WebP, up to %d MB.',
            trim($guidance),
            (int) round($this->getMaxSize() / 1024),
        )));
    }
}
