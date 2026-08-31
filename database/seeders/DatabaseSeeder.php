<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Fills a fresh install with a complete, working demo practice.
 *
 * Everything is written with updateOrCreate, so running the seeders again is
 * always safe and never duplicates anything.
 *
 *     php artisan migrate --seed
 *     php artisan db:seed          # again, any time
 *
 * >>> EVERY PERSON, PHOTOGRAPH AND WORD OF CONTENT IS INVENTED. <<<
 * See the note in each seeder. Replace all of it from the admin panel before a
 * real practice goes live.
 *
 * Order matters in two places, and only two:
 *
 *   1. PlaceholderImageSeeder must run before the seeders that reference
 *      image paths, or the site renders with broken pictures.
 *   2. AvailabilitySeeder must run before PatientSeeder, because the demo
 *      appointments are booked into real generated slots rather than invented
 *      times — which is what keeps the seeded bookings consistent with what the
 *      booking calendar will offer.
 */
class DatabaseSeeder extends Seeder
{
    /** The admin account documented in the README. */
    public const ADMIN_EMAIL = 'admin@example.com';

    public const ADMIN_PASSWORD = 'password';

    public function run(): void
    {
        $this->createAdminUser();

        $this->call([
            // Images first — later seeders reference their paths.
            PlaceholderImageSeeder::class,

            DoctorProfileSeeder::class,
            ServiceSeeder::class,
            TestimonialSeeder::class,
            BlogPostSeeder::class,
            GalleryImageSeeder::class,
            FaqSeeder::class,
            HealthVideoSeeder::class,

            // Availability before patients — see the note above.
            AvailabilitySeeder::class,
            PatientSeeder::class,

            // Last: it writes descriptions for pages whose content the seeders
            // above have just created.
            SeoSeeder::class,
        ]);

        $this->summarise();
    }

    /**
     * One staff account for the admin panel.
     *
     * There is no staff registration page anywhere in the application, by
     * design — accounts are made here or with `php artisan make:filament-user`.
     */
    private function createAdminUser(): void
    {
        User::query()->updateOrCreate(
            ['email' => self::ADMIN_EMAIL],
            [
                'name' => 'Dr. Tahmina Rahman',
                'password' => Hash::make(self::ADMIN_PASSWORD),
                'email_verified_at' => now(),
            ],
        );
    }

    /** Print the demo credentials, so nobody has to go looking for them. */
    private function summarise(): void
    {
        $this->command?->newLine();
        $this->command?->info('Demo practice seeded.');
        $this->command?->newLine();

        $this->command?->table(
            ['Sign in at', 'Email', 'Password'],
            [
                ['/admin (staff)', self::ADMIN_EMAIL, self::ADMIN_PASSWORD],
                ['/patient/login', PatientSeeder::DEMO_EMAIL, PatientSeeder::DEMO_PASSWORD],
            ],
        );

        $this->command?->warn('Change both passwords before this site goes anywhere near the public internet.');
        $this->command?->newLine();
    }
}
