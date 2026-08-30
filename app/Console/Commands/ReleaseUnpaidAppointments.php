<?php

namespace App\Console\Commands;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use Illuminate\Console\Command;

/**
 * Frees seats held for a payment that never arrived.
 *
 * A patient sent to the gateway holds their seat so nobody takes it while they
 * are typing card details. If they close the tab, that hold has to lapse.
 *
 * This is the SECOND of two mechanisms doing that job. BookingService also
 * releases expired holds lazily whenever anybody books, because most buyers
 * run on shared hosting with no cron and the availability page must never
 * offer a seat the insert would then refuse.
 *
 * This command exists for buyers who *do* have a scheduler, where it tidies up
 * promptly rather than waiting for the next booking attempt. It is registered
 * in routes/console.php.
 */
class ReleaseUnpaidAppointments extends Command
{
    protected $signature = 'appointments:release-unpaid';

    protected $description = 'Cancel unpaid bookings whose payment window has passed, freeing their slot';

    public function handle(): int
    {
        $released = 0;

        /*
         | Chunked because this runs unattended and a neglected site could have
         | accumulated a lot of them. `chunkById` rather than `chunk`: the
         | update changes the rows being paged over, and offset paging would
         | skip half of them.
         */
        Appointment::query()
            ->expiredHolds()
            ->chunkById(100, function ($appointments) use (&$released): void {
                foreach ($appointments as $appointment) {
                    $appointment->markStatus(AppointmentStatus::Cancelled);
                    $appointment->cancelled_at = now();
                    $appointment->cancellation_reason = 'Payment was not completed in time.';
                    $appointment->hold_expires_at = null;
                    $appointment->save();

                    $released++;
                }
            });

        /*
         | Deliberately silent towards the patient.
         |
         | They abandoned a checkout minutes ago and know perfectly well they
         | did not finish. An email saying their appointment was cancelled
         | would be the first they hear of ever having had one, and would send
         | them to the chamber confused.
         */
        $this->info($released === 0
            ? 'No unpaid holds needed releasing.'
            : "Released {$released} unpaid booking(s).");

        return self::SUCCESS;
    }
}
