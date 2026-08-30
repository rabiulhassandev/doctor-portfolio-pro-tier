<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Copies the demo photography onto the `public` disk.
 *
 * The files live in database/seeders/media and are committed to the repository,
 * so seeding never touches the network and a buyer with no internet still gets
 * a finished-looking site.
 *
 * They are photographs rather than generated artwork because a clinic site with
 * abstract shapes where the pictures should be does not look finished — it looks
 * unfinished in a way no amount of layout can fix. They are also JPEGs rather
 * than SVGs, which matters more than it looks: the admin panel's upload field
 * previews an image by drawing it into a canvas and hangs the crop editor off
 * that preview, and neither works for a vector file.
 *
 * >>> These are stock photographs licensed for reuse, not pictures of any real
 * >>> practice. Replace every one of them from the admin panel before launch.
 *
 * Delete storage/app/public/{doctor,gallery,blog,site} to clear them.
 */
class PlaceholderImageSeeder extends Seeder
{
    /** Where the committed source files live. */
    private const SOURCE = __DIR__.'/media';

    public function run(): void
    {
        $disk = Storage::disk('public');

        foreach ($this->files() as $relativePath) {
            $disk->put($relativePath, file_get_contents(self::SOURCE.'/'.$relativePath));
        }
    }

    /**
     * Every image under database/seeders/media, as disk-relative paths.
     *
     * @return list<string>
     */
    private function files(): array
    {
        if (! is_dir(self::SOURCE)) {
            throw new RuntimeException(
                'Demo images are missing from '.self::SOURCE.'. They ship with the repository — '
                .'restore them, or point the seeders at your own photographs.'
            );
        }

        $files = [];

        $tree = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(self::SOURCE, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($tree as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'jpg') {
                // Normalise Windows separators — these become URL paths.
                $files[] = str_replace('\\', '/', substr($file->getPathname(), strlen(self::SOURCE) + 1));
            }
        }

        sort($files);

        return $files;
    }
}
