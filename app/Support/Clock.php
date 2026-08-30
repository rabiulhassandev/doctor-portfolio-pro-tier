<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The clinic's wall clock.
 *
 * >>> READ THIS BEFORE TOUCHING ANY DATE CODE. <<<
 *
 * The rule this whole application follows is: **store UTC, display local.**
 *
 *   - config/app.php stays at 'UTC'. Every timestamp in the database is UTC.
 *     Do not change it. Eloquent, Filament and most packages assume it, and a
 *     locale-naive column silently corrupts history the day the site moves
 *     host or the country adopts daylight saving.
 *
 *   - config('booking.timezone') is where the clinic actually is. It is the
 *     only timezone the interface ever renders or parses in.
 *
 * Booking code therefore never calls now(), today() or Carbon::parse()
 * directly — those would answer in UTC and quietly offer a patient in Dhaka a
 * midnight appointment. It calls the methods below instead.
 *
 * That is the entire timezone story. One class, five methods.
 */
final class Clock
{
    /** The clinic's timezone identifier, e.g. 'Asia/Dhaka'. */
    public static function timezone(): string
    {
        return config('booking.timezone', 'UTC');
    }

    /**
     * Right now, as the clinic's wall clock reads it.
     *
     * Built from Laravel's now() rather than from scratch so that
     * Carbon::setTestNow() in a test still controls it.
     */
    public static function now(): CarbonImmutable
    {
        return CarbonImmutable::instance(now())->setTimezone(self::timezone());
    }

    /** Midnight this morning, clinic time. */
    public static function today(): CarbonImmutable
    {
        return self::now()->startOfDay();
    }

    /**
     * Read a string the patient typed or the form posted **as clinic time**.
     *
     * This is the important one. '2026-09-12 18:30' means half six in the
     * evening at the chamber; parsing it in UTC would place it somewhere else
     * entirely, and the bug only shows up as appointments landing on the wrong
     * day for patients near midnight.
     */
    public static function parse(string $value): CarbonImmutable
    {
        return CarbonImmutable::parse($value, self::timezone());
    }

    /**
     * Convert a timestamp Eloquent just handed back (UTC) into clinic time,
     * ready to be formatted for a human.
     */
    public static function fromStorage(CarbonInterface $utc): CarbonImmutable
    {
        return CarbonImmutable::instance($utc)->setTimezone(self::timezone());
    }
}
