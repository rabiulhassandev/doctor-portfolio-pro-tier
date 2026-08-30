<?php

use App\Services\Payments\Gateways\PayAtClinicGateway;
use App\Services\Payments\Gateways\SslCommerzGateway;

/*
|--------------------------------------------------------------------------
| Booking, payments and messaging
|--------------------------------------------------------------------------
|
| Operational settings a *developer* sets once per install. Branding lives in
| config/site.php — keep the two apart, so a non-technical reseller can edit
| colours and a site name without ever opening this file.
|
| Everything sensitive is read from .env. See the README for the full list of
| variables and where to get sandbox credentials.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Clinic timezone
    |--------------------------------------------------------------------------
    |
    | Every timestamp is *stored* in UTC — config/app.php stays at 'UTC' and
    | should not be changed. This is the timezone the clinic actually works in,
    | and it is the only one the interface ever displays or parses.
    |
    | Nothing in the booking code calls now() directly; it all goes through
    | App\Support\Clock, which reads this value. That one small class is the
    | entire timezone story for whoever maintains this later.
    |
    | Why not just set app.timezone to the clinic's zone? Because it makes every
    | stored timestamp locale-naive: move the site to a host in another region,
    | or run it in a country with daylight saving, and the history silently
    | shifts. Store UTC, display local.
    |
    */

    'timezone' => env('CLINIC_TIMEZONE', 'Asia/Dhaka'),

    /*
    |--------------------------------------------------------------------------
    | How far ahead patients may book
    |--------------------------------------------------------------------------
    |
    | The developer default. The doctor can override it from the admin panel
    | (Doctor profile → Booking), and that override wins. Resolved in exactly
    | one place: AvailabilityService::horizonEnd().
    |
    */

    'horizon_days' => env('BOOKING_HORIZON_DAYS', 30),

    /*
     | The shortest notice the clinic will accept. A slot starting inside this
     | window is not offered, so a patient cannot book something ten minutes
     | from now that nobody will see in time.
     */
    'min_notice_minutes' => env('BOOKING_MIN_NOTICE_MINUTES', 120),

    /*
     | How close to the appointment a patient may still cancel it themselves.
     | Inside this window the dashboard shows a "call the chamber" note instead
     | of a cancel button — a no-show the clinic knew about is a slot they could
     | have given to someone else.
     */
    'cancellation_cutoff_hours' => env('BOOKING_CANCELLATION_CUTOFF_HOURS', 12),

    /*
    |--------------------------------------------------------------------------
    | What a new booking starts as
    |--------------------------------------------------------------------------
    |
    | 'pending'   — the doctor confirms each booking by hand. Safest default,
    |               and what most chambers actually want.
    | 'confirmed' — the slot is guaranteed the moment it is booked. Use this
    |               when the availability schedule is genuinely reliable.
    |
    | A successful online payment always confirms the appointment regardless of
    | this setting: the patient has paid, so the slot is theirs.
    |
    */

    'default_status' => env('BOOKING_DEFAULT_STATUS', 'pending'),

    /*
    |--------------------------------------------------------------------------
    | Payments
    |--------------------------------------------------------------------------
    */

    'payment' => [

        /*
         | Master switch. Off, and the fee is still shown but no payment step
         | appears anywhere — every booking behaves as "pay at the chamber".
         */
        'enabled' => env('PAYMENTS_ENABLED', true),

        /*
         | Whether payment is *required* to hold the slot. Left false on purpose:
         | a patient who abandons a gateway page should still end up with an
         | appointment the clinic can ring them about, not with nothing.
         */
        'required' => env('PAYMENTS_REQUIRED', false),

        /*
         | Which gateway the checkout screen pre-selects. Must be a key of the
         | `gateways` array below.
         */
        'default' => env('PAYMENT_GATEWAY', 'sslcommerz'),

        /*
         | The seat is held while the patient is away on the gateway's page.
         | If they never come back, the hold lapses and the slot is released —
         | lazily on the next booking attempt, and by the
         | `appointments:release-unpaid` command if the host has cron.
         */
        'hold_minutes' => env('PAYMENT_HOLD_MINUTES', 15),

        'currency' => env('PAYMENT_CURRENCY', 'BDT'),

        /*
        |----------------------------------------------------------------------
        | Gateways
        |----------------------------------------------------------------------
        |
        | >>> ADDING YOUR OWN GATEWAY <<<
        |
        |   1. Write a class implementing App\Contracts\PaymentGateway.
        |   2. Add an entry below with `driver` pointing at it.
        |   3. Point PAYMENT_GATEWAY at the new key in .env.
        |
        | Nothing in App\Services\Booking changes. The whole array is handed to
        | your constructor, so put whatever credentials you need in it.
        |
        | A gateway whose isConfigured() returns false is simply hidden from the
        | checkout screen rather than failing — so a fresh install with no
        | credentials still takes bookings.
        |
        */

        'gateways' => [

            'sslcommerz' => [
                'driver' => SslCommerzGateway::class,
                'label' => 'Pay online (card, bKash, Nagad, bank)',
                'store_id' => env('SSLCOMMERZ_STORE_ID'),
                'store_password' => env('SSLCOMMERZ_STORE_PASSWORD'),
                'sandbox' => env('SSLCOMMERZ_SANDBOX', true),
            ],

            'cash' => [
                'driver' => PayAtClinicGateway::class,
                'label' => 'Pay at the chamber',
                'enabled' => env('PAYMENT_ALLOW_PAY_AT_CLINIC', true),
            ],

        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | SMS / WhatsApp
    |--------------------------------------------------------------------------
    |
    | >>> THIS IS AN INTEGRATION POINT. The template ships no paid gateway. <<<
    |
    | The default 'null' driver writes what it *would* have sent to the log, so
    | you can see the messages during development without an account anywhere.
    |
    | To go live: either point `driver` at 'http' and fill in the credentials
    | below (works with most bulk-SMS REST APIs), or write your own class
    | implementing App\Contracts\SmsSender and name it here. WhatsApp Cloud API
    | satisfies the same interface — it needs no separate driver type.
    |
    | See app/Services/Sms/ExampleHttpSmsSender.php for a complete worked
    | example with the provider-specific lines clearly marked.
    |
    */

    'sms' => [

        'enabled' => env('SMS_ENABLED', false),

        'driver' => env('SMS_DRIVER', 'null'),   // 'null' | 'http' | your own key

        'http' => [
            'endpoint' => env('SMS_ENDPOINT'),
            'api_key' => env('SMS_API_KEY'),
            'sender_id' => env('SMS_SENDER_ID'),
        ],

    ],

];
