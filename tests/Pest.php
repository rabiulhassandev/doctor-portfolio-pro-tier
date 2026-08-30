<?php

use App\Support\Clock;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

/*
 | Every feature test gets a fresh, empty, in-memory SQLite database (see the
 | DB_CONNECTION lines in phpunit.xml). Nothing here touches the buyer's MySQL
 | data, and the suite needs no setup beyond `php artisan test`.
 */
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Freeze the clock at a known moment in the clinic's own timezone.
 *
 * Slot generation is entirely time-relative — "is this slot in the past?",
 * "is this inside the booking horizon?" — so a test that does not pin the
 * clock passes in the morning and fails after six in the evening. Every
 * booking test calls this first.
 *
 * Returns the frozen moment, in clinic time, so a test can build expectations
 * from it: `$now = freezeClinicClock(); $tomorrow = $now->addDay();`
 */
function freezeClinicClock(string $clinicDateTime = '2026-09-01 09:00:00'): CarbonImmutable
{
    $moment = CarbonImmutable::parse($clinicDateTime, config('booking.timezone'));

    Carbon::setTestNow($moment);

    return Clock::now();
}
