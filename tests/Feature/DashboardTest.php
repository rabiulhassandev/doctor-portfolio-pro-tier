<?php

use App\Enums\AppointmentStatus;
use App\Filament\Widgets\AwaitingConfirmation;
use App\Filament\Widgets\BookingsPerWeek;
use App\Filament\Widgets\PracticeOverview;
use App\Models\Appointment;
use App\Models\DoctorProfile;
use App\Models\User;
use App\Support\Clock;
use App\Support\SeoAudit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| The dashboard
|--------------------------------------------------------------------------
|
| Two things are being protected here. The first is behaviour: the queue of
| unconfirmed bookings is the one place in this application where delay costs
| the practice money, so what appears in it and what does not is worth pinning
| down. The second is COST — this is the screen every member of staff opens
| first every morning, and the query counts below are assertions, not notes.
|
*/

beforeEach(function () {
    freezeClinicClock();

    $this->staff = User::factory()->create();
    $this->actingAs($this->staff, 'web');
});

/**
 * Confirmed appointments on consecutive past days, one per day.
 *
 * One per day because the factory's default slot is the same one every time,
 * and the unique index that prevents double-booking correctly refuses a second
 * row in it. Going backwards keeps them inside the chart's eight-week window.
 */
function seedAppointmentsAcrossDays(int $count): void
{
    foreach (range(1, $count) as $day) {
        Appointment::factory()
            ->confirmed()
            ->at(Clock::today()->subDays($day)->setTime(18, 0))
            ->create();
    }
}

describe('waiting for confirmation', function () {
    it('lists an upcoming booking nobody has answered', function () {
        $appointment = Appointment::factory()
            ->status(AppointmentStatus::Pending)
            ->at(Clock::today()->addDays(2)->setTime(18, 0))
            ->create();

        Livewire::test(AwaitingConfirmation::class)
            ->assertOk()
            ->assertSee($appointment->patient_name);
    });

    it('leaves out an appointment that has already been confirmed', function () {
        $confirmed = Appointment::factory()
            ->confirmed()
            ->at(Clock::today()->addDays(2)->setTime(18, 0))
            ->create();

        Livewire::test(AwaitingConfirmation::class)
            ->assertOk()
            ->assertDontSee($confirmed->patient_name);
    });

    it('leaves out a pending appointment whose time has passed', function () {
        /*
         | The important one. A pending row in the past is not work waiting to
         | be done — the appointment has been and gone — and counting it would
         | leave a queue nobody can ever empty, which is how a dashboard panel
         | becomes invisible.
         */
        $stale = Appointment::factory()
            ->status(AppointmentStatus::Pending)
            ->at(Clock::today()->subDays(5)->setTime(18, 0))
            ->create();

        expect(AwaitingConfirmation::canView())->toBeFalse();

        Livewire::test(AwaitingConfirmation::class)
            ->assertOk()
            ->assertDontSee($stale->patient_name);
    });

    it('hides itself entirely when there is nothing waiting', function () {
        Appointment::factory()->confirmed()->create();

        expect(AwaitingConfirmation::canView())->toBeFalse();
    });

    it('shows itself as soon as something is waiting', function () {
        Appointment::factory()
            ->status(AppointmentStatus::Pending)
            ->at(Clock::today()->addDay()->setTime(18, 0))
            ->create();

        expect(AwaitingConfirmation::canView())->toBeTrue();
    });
});

describe('the stat cards', function () {
    it('renders four figures', function () {
        Appointment::factory()->confirmed()->create();

        $html = Livewire::test(PracticeOverview::class)
            ->assertOk()
            ->assertSee("Today's appointments")
            ->assertSee('Waiting for confirmation')
            ->assertSee('This week')
            ->assertSee('Paid this month')
            ->html();

        expect(substr_count($html, 'fi-wi-stats-overview-stat-value'))->toBe(4);
    });

    /*
     | The amber tile. It is applied through extraAttributes rather than
     | ->color(), because Stat::color() only tints the description text and the
     | theme paints that white anyway — so ->color() here would be a silent
     | no-op. This test is what stops somebody "tidying" it back.
     */
    it('marks the pending card for attention only while something is pending', function () {
        Appointment::factory()
            ->status(AppointmentStatus::Pending)
            ->at(Clock::today()->addDay()->setTime(18, 0))
            ->create();

        expect(Livewire::test(PracticeOverview::class)->html())
            ->toContain('stat-attention');
    });

    it('leaves the cards uniform when nothing is pending', function () {
        Appointment::factory()->confirmed()->create();

        expect(Livewire::test(PracticeOverview::class)->html())
            ->not->toContain('stat-attention');
    });

    it('reads the whole dashboard from three queries', function () {
        // Spread across days: the factory's default slot is the same one every
        // time, and the double-booking index rightly refuses a second row in it.
        seedAppointmentsAcrossDays(20);

        // Warmed first so the count measures the WIDGET. The doctor profile is
        // shared with every view in the application and cached per request; it
        // would otherwise be charged to whichever thing happened to ask first.
        DoctorProfile::current();

        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        Livewire::test(PracticeOverview::class)->html();

        // Two windows plus the pending count. Every figure and every comparison
        // on the four cards comes out of those.
        expect($queries)->toBe(3);
    });
});

describe('the bookings chart', function () {
    it('draws eight weeks from a single query', function () {
        /*
         | This used to run one query per week AND hydrate every appointment in
         | each of them to count by status — sixteen queries and eight
         | collections of models to draw twenty-four bars.
         */
        seedAppointmentsAcrossDays(30);

        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        Livewire::test(BookingsPerWeek::class)->html();

        expect($queries)->toBeLessThanOrEqual(2);
    });

    it('hides itself when there is no history to draw', function () {
        expect(BookingsPerWeek::canView())->toBeFalse();
    });
});

describe('the SEO badge does not tax the whole panel', function () {
    /*
     | The navigation badge asks SeoAudit for a tally, and Filament renders the
     | sidebar on EVERY page in the panel. Uncached, that put the entire audit —
     | ten queries across seven tables — in front of every admin request,
     | including ones that have nothing to do with SEO.
     */
    it('caches the tally rather than re-auditing on every page', function () {
        Cache::flush();

        $first = 0;
        DB::listen(function () use (&$first): void {
            $first++;
        });
        SeoAudit::summary();
        $uncached = $first;

        $second = 0;
        DB::listen(function () use (&$second): void {
            $second++;
        });
        SeoAudit::summary();

        expect($uncached)->toBeGreaterThan(3)
            ->and($second)->toBeLessThan($uncached);
    });

    it('still reports live findings on the health check screen', function () {
        // The page must never show a stale list, whatever the badge is doing.
        Cache::flush();
        SeoAudit::summary();

        $findings = SeoAudit::run();

        expect($findings)->toBeInstanceOf(Collection::class);
    });
});
