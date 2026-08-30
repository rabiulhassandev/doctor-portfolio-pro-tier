<?php

namespace App\Console\Commands;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Services\Notifications\AppointmentNotifier;
use App\Support\Clock;
use Illuminate\Console\Command;

/**
 * Reminds patients about tomorrow's appointments.
 *
 * Needs a scheduler. Buyers without cron simply never send these and nothing
 * else in the application is affected — which is exactly why reminders are a
 * command rather than something the booking flow schedules for itself.
 *
 * Registered in routes/console.php; see the README for the one crontab line a
 * buyer needs.
 */
class SendAppointmentReminders extends Command
{
    protected $signature = 'appointments:send-reminders {--hours=24 : How far ahead to look}';

    protected $description = 'Email and text patients whose appointment is coming up';

    public function handle(AppointmentNotifier $notifier): int
    {
        $hours = max(1, (int) $this->option('hours'));

        $from = Clock::now();
        $to = $from->addHours($hours);

        $appointments = Appointment::query()
            ->whereBetween('starts_at', [$from->utc(), $to->utc()])
            ->whereIn('status', [
                AppointmentStatus::Pending->value,
                AppointmentStatus::Confirmed->value,
            ])
            /*
             | Only once per appointment. Without this a command running hourly
             | would text the same patient twenty-four times, which is worse
             | than not reminding them at all.
             */
            ->whereNull('reminded_at')
            ->get();

        foreach ($appointments as $appointment) {
            $notifier->reminder($appointment);

            // Stamped even if delivery failed. The notifier logs failures and
            // never throws; retrying an unreachable mail server every hour
            // would just fill the log.
            $appointment->forceFill(['reminded_at' => now()])->save();
        }

        $this->info($appointments->isEmpty()
            ? 'No reminders were due.'
            : 'Sent '.$appointments->count().' reminder(s).');

        return self::SUCCESS;
    }
}
