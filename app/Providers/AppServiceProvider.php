<?php

namespace App\Providers;

use App\Contracts\SmsSender;
use App\Models\DoctorProfile;
use App\Services\Payments\PaymentManager;
use App\Services\Sms\ExampleHttpSmsSender;
use App\Services\Sms\NullSmsSender;
use App\Support\Clock;
use Filament\Support\Facades\FilamentTimezone;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PaymentManager::class);

        /*
         | The SMS / WhatsApp integration point.
         |
         | The default writes messages to the log so a developer can see exactly
         | what would have been sent without holding an account anywhere. A
         | buyer swaps in their provider by writing one class implementing
         | App\Contracts\SmsSender and naming it in config/booking.php — nothing
         | in the booking or notification code changes.
         |
         | See app/Services/Sms/ExampleHttpSmsSender.php for a worked example.
         */
        $this->app->singleton(SmsSender::class, function (): SmsSender {
            return match (config('booking.sms.driver', 'null')) {
                'http' => new ExampleHttpSmsSender(config('booking.sms.http', [])),
                default => new NullSmsSender,
            };
        });
    }

    public function boot(): void
    {
        /*
         | Every public page — navbar, footer, hero, contact details — needs the
         | doctor's profile. Sharing it here means no controller has to remember
         | to pass it, and DoctorProfile::current() caches it so this costs one
         | query per request no matter how many views use it.
         |
         | Views refer to it simply as $doctor.
         */
        View::composer('*', function ($view): void {
            $view->with('doctor', DoctorProfile::current());
        });

        // Tailwind-friendly pagination markup for the blog and video lists.
        Paginator::useTailwind();

        /*
         | Show admin tables and forms in the clinic's own timezone.
         |
         | Timestamps are stored in UTC (see App\Support\Clock). Without this
         | line Filament would render them as stored, and a receptionist would
         | be reading appointment times six hours out — which is exactly the
         | sort of bug nobody notices until a patient arrives at the wrong time.
         */
        FilamentTimezone::set(Clock::timezone());
    }
}
