<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduled tasks
|--------------------------------------------------------------------------
|
| Both of these are OPTIONAL. The site works correctly without a scheduler —
| expired payment holds are also released lazily whenever anybody books, and a
| practice that never sends reminders is simply a practice that never sends
| reminders.
|
| If the buyer's hosting does offer cron, one line enables both:
|
|     * * * * * cd /path/to/the/site && php artisan schedule:run >> /dev/null 2>&1
|
| See the README.
|
*/

/*
 | Every ten minutes. The payment hold is fifteen by default, so a seat is
 | never left blocked for much longer than the window it was promised for.
 */
Schedule::command('appointments:release-unpaid')
    ->everyTenMinutes()
    ->withoutOverlapping();

/*
 | Once a day, in the early evening — late enough that a patient reading it has
 | time to ring the chamber before it closes, rather than at eight in the
 | morning when it is not yet open.
 |
 | The command only looks 24 hours ahead and marks each appointment as
 | reminded, so running it more often would not send duplicates.
 */
Schedule::command('appointments:send-reminders')
    ->dailyAt('17:00')
    ->timezone(config('booking.timezone'))
    ->withoutOverlapping();
