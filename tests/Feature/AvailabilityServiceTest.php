<?php

use App\Models\Appointment;
use App\Models\AvailabilityBlackout;
use App\Models\AvailabilitySlot;
use App\Models\DoctorProfile;
use App\Services\Booking\AvailabilityService;
use App\Support\Clock;
use App\Support\Slot;
use Carbon\CarbonImmutable;

/*
|--------------------------------------------------------------------------
| Slot generation
|--------------------------------------------------------------------------
|
| The rules being verified here are the ones written out at the top of
| AvailabilityService. If one of these fails, read that doc block first — the
| behaviour is specified there, not inferred from the code.
|
| Every test freezes the clock, because slot generation is entirely
| time-relative: without it these pass in the morning and fail after six.
|
*/

beforeEach(function () {
    // A Tuesday, 9am at the chamber.
    $this->now = freezeClinicClock('2026-09-01 09:00:00');
    $this->service = app(AvailabilityService::class);

    // Rules are cached for five minutes; a fresh store per test keeps one
    // test's schedule from leaking into the next.
    Cache::flush();
});

/** The next date falling on the given Carbon weekday (0 = Sunday). */
function nextWeekday(int $dayOfWeek): CarbonImmutable
{
    $date = Clock::today()->addDay();

    while ($date->dayOfWeek !== $dayOfWeek) {
        $date = $date->addDay();
    }

    return $date;
}

it('expands a weekly rule into evenly spaced slots', function () {
    AvailabilitySlot::factory()->weeklyOn(0)->between('18:00', '21:00')->duration(30)->create();

    $slots = $this->service->slotsForDate(nextWeekday(0));

    // 6pm to 9pm in half hours is six appointments.
    expect($slots)->toHaveCount(6)
        ->and($slots->first()->label())->toBe('6:00 PM')
        ->and($slots->last()->label())->toBe('8:30 PM')
        ->and($slots->first()->durationMinutes())->toBe(30);
});

it('offers nothing on a weekday with no rule', function () {
    AvailabilitySlot::factory()->weeklyOn(0)->create();

    expect($this->service->slotsForDate(nextWeekday(3)))->toBeEmpty();
});

it('ignores a rule that has been switched off', function () {
    AvailabilitySlot::factory()->weeklyOn(0)->inactive()->create();

    expect($this->service->slotsForDate(nextWeekday(0)))->toBeEmpty();
});

it('only offers whole appointments that fit inside the block', function () {
    // 6pm–9pm is 180 minutes; in 50-minute slots that is three, ending 8:30.
    AvailabilitySlot::factory()->weeklyOn(0)->between('18:00', '21:00')->duration(50)->create();

    $slots = $this->service->slotsForDate(nextWeekday(0));

    expect($slots)->toHaveCount(3)
        ->and($slots->last()->endsAt->format('g:i A'))->toBe('8:30 PM');
});

describe('precedence', function () {
    it('gives a blackout the final word over every rule', function () {
        $date = nextWeekday(0);

        AvailabilitySlot::factory()->weeklyOn(0)->create();
        // Even a date-specific rule cannot reopen a blacked-out day.
        AvailabilitySlot::factory()->onDate($date)->between('10:00', '12:00')->create();
        AvailabilityBlackout::factory()->on($date)->create(['reason' => 'Eid holiday']);

        expect($this->service->slotsForDate($date))->toBeEmpty()
            ->and($this->service->blackoutFor($date)?->reason)->toBe('Eid holiday');
    });

    it('keeps a blacked-out day off the calendar as well as out of the slot list', function () {
        /*
         | Regression guard.
         |
         | slotsForDate() and slotsForRange() answer the blackout question by
         | different routes — one in SQL, one in PHP — and they once disagreed:
         | the PHP path compared clinic-midnight against UTC-midnight, so the
         | calendar offered a day that then refused every click.
         |
         | Both must agree, which is what makes this worth asserting twice.
         */
        $date = nextWeekday(0);

        AvailabilitySlot::factory()->weeklyOn(0)->create();
        AvailabilityBlackout::factory()->on($date)->create();

        expect($this->service->slotsForDate($date))->toBeEmpty()
            ->and($this->service->slotsForRange($date, $date)->get($date->toDateString()))->toBeEmpty()
            ->and($this->service->bookableDates()->contains(fn ($d) => $d->isSameDay($date)))->toBeFalse();
    });

    it('lets a date-specific rule replace the normal weekly hours', function () {
        $date = nextWeekday(0);

        AvailabilitySlot::factory()->weeklyOn(0)->between('18:00', '21:00')->create();
        AvailabilitySlot::factory()->onDate($date)->between('10:00', '12:00')->duration(30)->create();

        $slots = $this->service->slotsForDate($date);

        // The evening is gone entirely — "instead of", not "as well as".
        expect($slots)->toHaveCount(4)
            ->and($slots->first()->label())->toBe('10:00 AM')
            ->and($slots->last()->label())->toBe('11:30 AM');
    });

    it('lets a date-specific rule extend the weekly hours when asked to', function () {
        $date = nextWeekday(0);

        AvailabilitySlot::factory()->weeklyOn(0)->between('18:00', '20:00')->duration(30)->create();
        AvailabilitySlot::factory()->extraOnDate($date)->between('10:00', '11:00')->duration(30)->create();

        $slots = $this->service->slotsForDate($date);

        // Both blocks, in time order.
        expect($slots)->toHaveCount(6)
            ->and($slots->first()->label())->toBe('10:00 AM')
            ->and($slots->last()->label())->toBe('7:30 PM');
    });

    it('does not offer the same time twice when two rules overlap', function () {
        $date = nextWeekday(0);

        AvailabilitySlot::factory()->weeklyOn(0)->between('18:00', '20:00')->duration(30)->create();
        AvailabilitySlot::factory()->weeklyOn(0)->between('19:00', '21:00')->duration(30)->capacity(3)->create();

        $slots = $this->service->slotsForDate($date);

        expect($slots->pluck('startsAt')->map->format('H:i')->all())
            ->toBe(['18:00', '18:30', '19:00', '19:30', '20:00', '20:30'])
            // The overlap takes the more generous capacity.
            ->and($slots->firstWhere(fn (Slot $s) => $s->label() === '7:00 PM')->capacity)->toBe(3);
    });
});

describe('subtractions', function () {
    it('removes a slot once its only place is booked', function () {
        $date = nextWeekday(0);
        AvailabilitySlot::factory()->weeklyOn(0)->between('18:00', '19:00')->duration(30)->create();

        Appointment::factory()->at($date->setTime(18, 0))->create();

        $slots = $this->service->slotsForDate($date);

        expect($slots)->toHaveCount(1)
            ->and($slots->first()->label())->toBe('6:30 PM');
    });

    it('keeps a slot open until every seat is taken', function () {
        $date = nextWeekday(0);
        AvailabilitySlot::factory()->weeklyOn(0)->between('18:00', '18:30')->duration(30)->capacity(3)->create();

        Appointment::factory()->at($date->setTime(18, 0))->seat(1)->create();
        Appointment::factory()->at($date->setTime(18, 0))->seat(2)->create();

        $slot = $this->service->slotsForDate($date)->first();

        expect($slot->remaining())->toBe(1)
            ->and($slot->scarcityLabel())->toBe('Last place');
    });

    it('does not count a cancelled booking against capacity', function () {
        $date = nextWeekday(0);
        AvailabilitySlot::factory()->weeklyOn(0)->between('18:00', '18:30')->duration(30)->create();

        Appointment::factory()->at($date->setTime(18, 0))->cancelled()->create();

        expect($this->service->slotsForDate($date))->toHaveCount(1);
    });

    it('releases a seat whose payment hold has lapsed', function () {
        $date = nextWeekday(0);
        AvailabilitySlot::factory()->weeklyOn(0)->between('18:00', '18:30')->duration(30)->create();

        // The patient went to the gateway and never came back.
        Appointment::factory()->at($date->setTime(18, 0))->expiredHold()->create();

        expect($this->service->slotsForDate($date))->toHaveCount(1);
    });

    it('still holds a seat while the patient is at the gateway', function () {
        $date = nextWeekday(0);
        AvailabilitySlot::factory()->weeklyOn(0)->between('18:00', '18:30')->duration(30)->create();

        Appointment::factory()->at($date->setTime(18, 0))->onHold()->create();

        expect($this->service->slotsForDate($date))->toBeEmpty();
    });

    it('hides times that have passed or are inside the notice period', function () {
        // Today is a Tuesday and it is 9am. The rule runs 8am to noon.
        config()->set('booking.min_notice_minutes', 120);
        AvailabilitySlot::factory()
            ->weeklyOn(Clock::today()->dayOfWeek)
            ->between('08:00', '12:00')
            ->duration(60)
            ->create();

        $slots = $this->service->slotsForDate(Clock::today());

        // 8am has passed; 9, 10 are inside the two-hour notice; 11am survives.
        expect($slots)->toHaveCount(1)
            ->and($slots->first()->label())->toBe('11:00 AM');
    });
});

describe('the booking horizon', function () {
    it('offers nothing beyond the configured horizon', function () {
        config()->set('booking.horizon_days', 7);
        AvailabilitySlot::factory()->weeklyOn(0)->create();

        $farFuture = Clock::today()->addDays(30);
        while ($farFuture->dayOfWeek !== 0) {
            $farFuture = $farFuture->addDay();
        }

        expect($this->service->slotsForDate($farFuture))->toBeEmpty();
    });

    it("lets the doctor's own setting override the developer default", function () {
        config()->set('booking.horizon_days', 30);
        DoctorProfile::create([
            'name' => 'Dr. Test',
            'specialization' => 'Cardiology',
            'booking_horizon_days' => 3,
        ]);
        DoctorProfile::forgetCurrent();

        expect($this->service->horizonEnd()->toDateString())
            ->toBe(Clock::today()->addDays(3)->toDateString());
    });

    it('never offers a date in the past', function () {
        AvailabilitySlot::factory()->weeklyOn(0)->create();

        expect($this->service->slotsForDate(Clock::today()->subWeek()))->toBeEmpty();
    });
});

it('lists only the dates that actually have a place left', function () {
    config()->set('booking.horizon_days', 14);

    AvailabilitySlot::factory()->weeklyOn(0)->between('18:00', '18:30')->duration(30)->create();

    $dates = $this->service->bookableDates();

    // Two Sundays inside a fortnight.
    expect($dates)->toHaveCount(2);

    // Fill the first one and it drops off the calendar.
    Appointment::factory()->at($dates->first()->setTime(18, 0))->create();

    expect($this->service->bookableDates())->toHaveCount(1);
});

describe('findBookableSlot', function () {
    it('resolves a posted time back into a real slot', function () {
        $date = nextWeekday(0);
        AvailabilitySlot::factory()->weeklyOn(0)->between('18:00', '19:00')->duration(30)->capacity(2)->create();

        $slot = $this->service->findBookableSlot($date->setTime(18, 30));

        // Capacity and duration come from the rule, never from the form.
        expect($slot)->not->toBeNull()
            ->and($slot->capacity)->toBe(2)
            ->and($slot->durationMinutes())->toBe(30);
    });

    it('refuses a time that no rule ever offered', function () {
        $date = nextWeekday(0);
        AvailabilitySlot::factory()->weeklyOn(0)->between('18:00', '19:00')->duration(30)->create();

        // 6:15 is not on the half hour — a hand-edited form submission.
        expect($this->service->findBookableSlot($date->setTime(18, 15)))->toBeNull();
    });

    it('refuses a time that is already full', function () {
        $date = nextWeekday(0);
        AvailabilitySlot::factory()->weeklyOn(0)->between('18:00', '18:30')->duration(30)->create();

        Appointment::factory()->at($date->setTime(18, 0))->create();

        expect($this->service->findBookableSlot($date->setTime(18, 0)))->toBeNull();
    });
});

it('caches availability rules as plain rows, not as models', function () {
    /*
     | Regression guard.
     |
     | Caching Eloquent models means serialising them, and every cache store a
     | buyer will actually run — database, file, redis — really does serialise.
     | When the class cannot be resolved on the way back out you get
     | __PHP_Incomplete_Class and the booking calendar dies. The test suite runs
     | on the `array` store, which does NOT serialise, so this failure mode is
     | invisible unless it is asserted directly.
     */
    AvailabilitySlot::factory()->weeklyOn(0)->create();

    $this->service->bookableDates();

    $cached = Cache::get('availability.rules');

    expect($cached)->toBeArray()
        ->and($cached[0] ?? null)->toBeArray()
        ->and($cached[0])->toHaveKey('start_time');
});

it('builds a whole month without running a query per day', function () {
    config()->set('booking.horizon_days', 60);
    AvailabilitySlot::factory()->weeklyOn(0)->between('18:00', '19:00')->duration(30)->create();

    DB::enableQueryLog();
    $days = $this->service->slotsForRange(Clock::today(), Clock::today()->addDays(29));
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($days)->toHaveCount(30)
        // Blackouts, rules, bookings — plus the profile read for the horizon.
        // The point is that it does not grow with the number of days.
        ->and($queries)->toBeLessThanOrEqual(5);
});
