<?php

/*
|--------------------------------------------------------------------------
| Architecture rules
|--------------------------------------------------------------------------
|
| These are the boundaries a buyer's developer needs to keep intact for the
| template to stay swappable. They are tested rather than merely documented,
| because a rule that only exists in a comment is a rule that gets broken in a
| code review nobody schedules.
|
*/

test('booking logic never reaches into a specific payment gateway', function () {
    // The whole point of App\Contracts\PaymentGateway is that a buyer can swap
    // SSLCommerz for their own processor by writing one class. That only holds
    // if the booking layer has never heard of any particular one.
    expect('App\Services\Booking')
        ->not->toUse('App\Services\Payments\Gateways');
});

test('booking logic never reaches into a specific SMS provider', function () {
    expect('App\Services\Booking')
        ->not->toUse('App\Services\Sms');
});

test('notifications reach messaging providers only through the contract', function () {
    expect('App\Services\Notifications')
        ->not->toUse('App\Services\Sms');
});

test('the slot generator stays pure', function () {
    // No database, no clock, no config — that is what makes it testable and
    // what keeps the fiddly arithmetic separable from the querying.
    expect('App\Services\Booking\SlotGenerator')
        ->not->toUse([
            'Illuminate\Support\Facades\DB',
            'Illuminate\Support\Facades\Cache',
            'Illuminate\Support\Facades\Config',
            'App\Support\Clock',
        ]);
});

test('support classes do not depend on the service layer', function () {
    expect('App\Support')
        ->not->toUse('App\Services');
});

test('enums carry no infrastructure dependencies', function () {
    expect('App\Enums')
        ->not->toUse(['Illuminate\Support\Facades\DB', 'App\Services']);
});

test('models do not send notifications directly', function () {
    /*
     | The rule stated throughout this codebase: pure normalisation belongs in
     | a model hook; anything with a side effect belongs in an explicit service
     | call. Notifications are the side effect this most often tempts people
     | into — see the doc block on AppointmentWorkflow for why.
     |
     | Patient is excluded: Laravel's password-reset contract requires
     | sendPasswordResetNotification() to live on the model itself.
     */
    expect('App\Models')
        ->not->toUse('App\Services\Notifications')
        ->ignoring('App\Models\Patient');
});
