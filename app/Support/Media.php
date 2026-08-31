<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Turns a stored file path into a URL the browser can load.
 *
 * Everything the doctor uploads through Filament lands on the `public` disk as
 * a relative path like `gallery/clinic-front.jpg`. Views never build that URL by
 * hand — they call Media::url() so uploads, seeded placeholder files and the
 * "nothing uploaded yet" case are all handled in one place.
 *
 * Medical documents are the deliberate exception: they live on the private
 * `medical` disk and are never given a URL at all. They are streamed by
 * MedicalDocumentController after an authorisation check. See that class.
 *
 * Remember to run `php artisan storage:link` once per install, or uploaded
 * files will 404.
 */
class Media
{
    /**
     * Public URL for an uploaded file, or null when nothing was uploaded.
     *
     * The result is root-relative (`/storage/gallery/front.jpg`) because that is
     * how the `public` disk is configured — see config/filesystems.php for why.
     * That is exactly what an <img> tag wants. Anything that has to travel
     * outside the page, such as a meta tag, wants {@see absoluteUrl()} instead.
     *
     * Paths that are already absolute URLs are returned untouched, which lets a
     * seeder point at a remote image without any special-casing.
     */
    public static function url(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://', '/'])) {
            return $path;
        }

        return Storage::disk('public')->url($path);
    }

    /**
     * The photograph behind an interior page's heading band.
     *
     * Resolution order, first hit wins:
     *
     *   1. whatever the view passed explicitly;
     *   2. `site.banners.<current route name>`;
     *   3. `site.banners.default`.
     *
     * Returns null when none of those is set, and the band falls back to its
     * plain dark treatment. That is a supported outcome, not a failure — it is
     * what a fresh install looks like before the seeders have copied the demo
     * photographs onto the disk.
     *
     * `$key` overrides the route name for the screens that are not a route of
     * their own, such as the shared patient sign-in shell.
     */
    public static function banner(?string $explicit = null, ?string $key = null): ?string
    {
        if (filled($explicit)) {
            return static::url($explicit);
        }

        $banners = config('site.banners', []);
        $key ??= request()->route()?->getName();

        return static::url($banners[$key] ?? $banners['default'] ?? null);
    }

    /**
     * The same URL with a scheme and host on the front.
     *
     * Facebook, X and Google read og:image and the schema.org blocks from a
     * server that has no idea which page the tag came from, so a relative path
     * is useless to them. `url()` resolves against the current request, which
     * keeps the address right on every domain the site answers to.
     */
    public static function absoluteUrl(?string $path): ?string
    {
        $url = static::url($path);

        if ($url === null || Str::startsWith($url, ['http://', 'https://'])) {
            return $url;
        }

        return url($url);
    }
}
