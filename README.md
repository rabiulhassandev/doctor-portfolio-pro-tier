# Single Doctor Portfolio — Pro Tier

A complete digital practice for one doctor: a marketing website, real-time
appointment booking, patient accounts, online payments, a patient-education
video library, and secure delivery of prescriptions and reports.

The doctor runs all of it themselves from an admin panel. No developer is
needed to change a price, add a video, close the chamber for a week, or upload
a patient's results.

**Stack:** Laravel 13 · Filament 4 · Livewire 3 · Tailwind CSS 4 · Alpine.js · MySQL · Vite

---

## Contents

- [What the Pro tier adds](#what-the-pro-tier-adds)
- [Requirements](#requirements)
- [Installation](#installation)
- [Signing in](#signing-in)
- [Environment variables](#environment-variables)
- [Rebranding for a new doctor](#rebranding-for-a-new-doctor)
- [How booking works](#how-booking-works)
- [Payments](#payments)
- [SMS and WhatsApp](#sms-and-whatsapp)
- [Health videos](#health-videos)
- [Medical documents](#medical-documents)
- [Sending email](#sending-email)
- [Scheduled tasks](#scheduled-tasks)
- [Deploying](#deploying)
- [Project layout](#project-layout)
- [Tests](#tests)

---

## What the Pro tier adds

Everything in the Standard tier — doctor profile, services, testimonials,
articles, gallery, contact page, SEO — plus:

| Feature | What it means |
|---|---|
| **Real-time booking** | Patients see the times actually free in the chamber and book one. No "we will call you back". |
| **Patient accounts** | Their own login, separate from staff. Upcoming appointments, history, and their documents. |
| **Online payments** | SSLCommerz (cards, bKash, Nagad, bank), with "pay at the chamber" always available as a fallback. |
| **Health video library** | Patient-education videos from YouTube, Vimeo or uploaded directly, filterable by condition. |
| **Document delivery** | Prescriptions and reports uploaded by the doctor, downloadable only by the patient they belong to. |
| **FAQ page** | With schema.org markup, so Google can show the answers directly in search results. |
| **Availability management** | Weekly hours, one-off changes, and days off — all from the admin panel. |

---

## Requirements

| Requirement | Version |
|---|---|
| PHP | 8.3 or newer, with `pdo_mysql`, `gd`, `intl`, `bcmath` |
| MySQL | 8.0 or newer (MariaDB 10.6+ also works) |
| Node.js | 20 or newer (only needed to build assets) |
| Composer | 2.x |

> `bcmath` is not optional. Payment amounts are compared with `bccomp()` rather
> than as floats, and that comparison is what stops a patient paying one taka
> for a fifteen-hundred-taka consultation.

---

## Installation

```bash
# 1. Install PHP and JavaScript dependencies
composer install
npm install

# 2. Create your environment file and generate an app key
cp .env.example .env
php artisan key:generate

# 3. Create the database, then point .env at it
#    DB_DATABASE=single_doctor_pro
#    DB_USERNAME=root
#    DB_PASSWORD=

# 4. Create the tables and fill the site with a working demo practice
php artisan migrate --seed

# 5. Link the uploads folder so images are publicly reachable
php artisan storage:link

# 6. Build the CSS and JavaScript
npm run build

# 7. Start the site
php artisan serve
```

The website is now at <http://localhost:8000> and the admin panel at
<http://localhost:8000/admin>.

> **`php artisan storage:link` is not optional.** Skip it and every uploaded
> photograph will 404.

### While developing

```bash
npm run dev        # Vite dev server with hot reload
php artisan serve  # in a second terminal
```

### About the demo content

> **The demo doctor is fictional and the demo photographs are stock.**
>
> Dr. Tahmina Rahman does not exist. The name, the chamber, the BMDC
> number, the patient quotes, the articles and the fees are all invented for the
> demo — the telephone numbers end in zeroes and the emails use the reserved
> `.example` domain, so nothing on a seeded demo site can reach a real person.
>
> The photographs in `database/seeders/media` are stock images licensed for
> commercial use. `doctor/portrait.jpg` and `doctor/consulting.jpg` are by
> Dr. Jyoti Bandi via Pexels ([36665076](https://www.pexels.com/photo/36665076/),
> [36665089](https://www.pexels.com/photo/36665089/)) under the Pexels licence,
> which permits commercial use without attribution — the credit is here because
> it is the decent thing, not because it is required. **The person in them is a
> real doctor who is not Tahmina Rahman.** She is standing in for a fictional
> character on a demo, and a buyer who ships the template with her face still on
> it is misrepresenting a real person.
>
> The seeded videos are real public YouTube and Vimeo videos, included only so
> the library has something that plays; they are not medical advice and have
> nothing to do with any practice. Some of the YouTube IDs have since been taken
> down and now show a grey placeholder thumbnail — replace them, or clear the
> table, before showing the demo to anybody.
>
> **Replace every photograph, every video and every word of this content from
> the admin panel before the site goes live.**

The demo is written for Bangladesh: degrees in the MBBS → BCS → FCPS → MD order,
a BMDC registration number, a Dhanmondi chamber, evening chamber hours with
Friday closed, a week that starts on Saturday, and SSLCommerz for payments. To
move the template to another market the things to change are the seeders,
`DoctorProfile::DAYS` (the week order), the `registration_label` field, and the
payment gateway.

---

## Signing in

The seeder creates two accounts:

| Sign in at | Email | Password |
|---|---|---|
| `/admin` — staff | `admin@example.com` | `password` |
| `/patient/login` — patient | `patient@example.com` | `password` |

**Change both before the site goes live.** Create a fresh staff account with:

```bash
php artisan make:filament-user
```

There is no staff registration page anywhere in the application, by design.
Staff accounts are only ever created from the command line.

### Two guards, deliberately

Patients live in the `patients` table on the `patient` guard. Staff live in
`users` on the `web` guard. They have separate tables, separate sessions and
separate password-reset token pools — a patient session can never satisfy an
admin route, and a patient registration can never create a row in the table that
grants access to `/admin`. Do not merge them to save a table.

---

## Environment variables

Everything below is in `.env.example` with comments. The ones that matter:

### Database and mail

```dotenv
DB_DATABASE=single_doctor_pro
DB_USERNAME=root
DB_PASSWORD=

MAIL_MAILER=smtp
MAIL_HOST=smtp.your-provider.com
MAIL_PORT=587
MAIL_USERNAME=your-username
MAIL_PASSWORD=your-password
MAIL_FROM_ADDRESS="noreply@your-clinic.com"
MAIL_FROM_NAME="${APP_NAME}"
```

### Clinic operations

```dotenv
# The timezone the chamber works in. Timestamps are always STORED in UTC;
# this is only what patients and staff see. Do not change the app timezone.
CLINIC_TIMEZONE=Asia/Dhaka

BOOKING_HORIZON_DAYS=30              # How far ahead patients may book
BOOKING_MIN_NOTICE_MINUTES=120       # Shortest notice accepted
BOOKING_CANCELLATION_CUTOFF_HOURS=12 # How late a patient may cancel online

# "pending" = the doctor confirms each booking by hand (safest default)
# "confirmed" = the slot is guaranteed immediately
BOOKING_DEFAULT_STATUS=pending
```

### Payments

```dotenv
PAYMENTS_ENABLED=true
PAYMENTS_REQUIRED=false              # Leave false — see Payments below
PAYMENT_GATEWAY=sslcommerz
PAYMENT_CURRENCY=BDT
PAYMENT_HOLD_MINUTES=15
PAYMENT_ALLOW_PAY_AT_CLINIC=true

# Free sandbox store: https://developer.sslcommerz.com/
SSLCOMMERZ_STORE_ID=
SSLCOMMERZ_STORE_PASSWORD=
SSLCOMMERZ_SANDBOX=true
```

Leave the SSLCommerz credentials blank and online payment is simply **hidden**
from the checkout screen. The site still takes bookings, payable at the chamber.

### SMS / WhatsApp

```dotenv
SMS_ENABLED=false
SMS_DRIVER=null                      # "null" logs instead of sending
SMS_ENDPOINT=
SMS_API_KEY=
SMS_SENDER_ID=
```

---

## Rebranding for a new doctor

Almost everything is edited in the admin panel. Only two things live in code.

### 1. Colours, site name and logo — `config/site.php`

**This is the file to edit per buyer.** It carries TWO palettes, and they are
deliberately different.

**The public website** is dark, photographic and brass-accented:

```php
colors => [
    night        => #0B1620,  // Hero, footer, calls to action
    night_soft   => #132433,  // Raised panels on the dark
    brass        => #C8A45C,  // The one accent, used once per view
    paper        => #F8F6F2,  // Light sections, for reading at length
    ink          => #111C26,  // Body copy
    // …
],
```

**The admin panel** is a bright blue working tool:

```php
admin => [
    primary      => #4F7FE8,  // Topbar, active states, stat cards
    sidebar      => #FFFFFF,  // The navigation column
    canvas       => #F4F6F9,  // The page behind the cards
    brand_tint   => #EAF0FD,  // Logo block and active menu item
],
```

A patient should feel they have arrived somewhere considered. A receptionist at
six in the evening wants contrast and legibility, not atmosphere. Trying to
serve both with one palette makes a worse job of each — which is why there are
two, and why a test asserts the public brass never appears inside `/admin`.

Both are injected as CSS custom properties at render time, so **changing a hex
code restyles its half of the system with no rebuild**. Leave `logo` as `null`
and the doctor's initials appear in a brass-ruled square instead, which looks
deliberate rather than broken.

Three things are worth knowing before reaching for a different palette:

- **Spend the brass once per view.** It marks the primary action, the active
  navigation item, or the rule under a heading — not all three. Brass on every
  icon and border is how a restrained palette turns gaudy.
- **Long-form text sits on `paper`, never on `night`.** A full page of body copy
  reversed out of near-black is hard work, and this site's readers are often
  older than average.
- **Every page opens with a dark band.** The navbar is transparent until you
  scroll, so a page with a light top would lose its own header. If you add one,
  give it a dark band.
- **The light sections are lists, not card grids.** Four three-column card grids
  stacked on top of each other is the single thing that most makes a site look
  like a template, however carefully each card is set. Services, FAQs, contact
  methods and qualifications are all hairline-separated rows (`.row-editorial`,
  with `.numeral-index` for the figures); articles and videos keep their
  photographs but drop the card chrome around them. Add `.paper-grain` to any
  new light section so it has the same tooth as the rest.

The same file carries the feature switches:

```php
features => [
    blog             => true,
    gallery          => true,
    testimonials     => true,
    faq              => true,
    health_videos    => true,
    booking          => true,   // Off = Standard-tier behaviour
    whatsapp_button  => true,
],
```

Turning one off hides the page, its navigation link **and** its sitemap entry —
without deleting code you might want back later.

### 2. Page banners — `config/site.php`

Every interior page opens with a full-bleed photograph under a dark overlay. The
picture is chosen **by route name**, so no page view names an image file:

```php
banners => [
    default        => site/dhaka.jpg,
    about          => doctor/consulting.jpg,
    services       => gallery/procedure-room.jpg,
    faq            => gallery/waiting-area.jpg,
    patient.auth   => gallery/chamber.jpg,   // the sign-in screens
    // …
],
```

Paths are relative to the `public` disk — the same place the admin panel's
uploads land — so a buyer can drop photographs into `storage/app/public` and
point these at them. An absolute URL works too.

Set the array to `[]` and every band falls back to its plain dark treatment,
which still looks finished. That is also what a fresh install looks like before
the seeders have run, so **a missing file is never a broken page**.

Choose dark, quiet photographs. A white-walled clinic shot goes grey under the
overlay and the heading stops being legible; a picture with a busy centre fights
the words. Rooms, corridors, equipment and cityscapes work.

One thing to keep in step: `<x-ui.page-hero>` takes a `width` prop (`wide` /
`medium` / `narrow`) that must match the container the page below it uses. Get it
wrong and the heading's left edge and the content's left edge disagree by a
couple of hundred pixels, which reads as two grids bolted together.

### 3. Site name — `.env`

```dotenv
APP_NAME="Dr. Amelia Hart"
```

### Type

| Role | Latin | Bengali | Tailwind class |
|---|---|---|---|
| Text | Manrope | SolaimanLipi | `font-sans` |
| Display | Cormorant Garamond | SolaimanLipi | `font-display` |
| Admin panel | Inter | — | (set in AdminPanelProvider) |

`h1` and `h2` pick up the display stack automatically; `h3` and below stay in
the text stack. Cormorant is a small-bodied, high-contrast face — it is
magnificent at 56px and spidery at 20px, so it is reserved for display sizes
and set at weight 300, where the contrast between thick and thin is the whole
point of using it. It also takes slightly POSITIVE tracking: tight tracking
suits a sturdy face, but on a delicate one it closes the counters and turns a
headline into a texture.

The admin panel uses neither. A dense table of appointment times wants an even,
unremarkable grotesque, so it is set in Inter.

No `lang` attribute is needed anywhere. Both stacks carry SolaimanLipi
immediately after the Latin face, so a line mixing Bangla and English picks the
right glyphs word by word.

The Latin faces are pulled from Bunny at build time and self-hosted, so no
request ever reaches Google. SolaimanLipi is committed under `resources/fonts/`
and declared at the top of `resources/css/app.css` — three details in that
`@font-face` block are load-bearing and the comments there explain each.

> SolaimanLipi was designed by Solaiman Karim in 2003 for the Ekushey project
> and is distributed freely. **Confirm the current terms before redistributing
> it inside a commercial product.** If in doubt, delete the two `@font-face`
> blocks and the files in `resources/fonts/` — the system Bengali fallbacks take
> over cleanly.

---

## How booking works

1. The doctor sets their hours under **Practice → My hours**: weekly blocks
   ("every Sunday, 6:30 to 9pm, half-hour appointments"), one-off changes for a
   single date, and how many patients may share a time.
2. Days off go under **Practice → Days off**. A blackout beats every other rule.
3. The public booking page shows only dates and times that are genuinely free.
4. A guest may choose a time, but must sign in or register to confirm — their
   chosen slot is held across the round trip.
5. The booking is created as `pending` or `confirmed` depending on
   `BOOKING_DEFAULT_STATUS`. A successful online payment always confirms it.
6. Both the patient and the chamber are emailed.

### Two patients cannot take the same seat

Two mechanisms, doing different jobs:

- A transaction with a row lock, so the common case produces a readable message
  ("someone else booked that just before you did") rather than a database error.
- **A unique index**, which is the actual guarantee. It holds even for a booking
  made from tinker, a seeder, or some future admin screen whose author forgot
  the booking service exists.

Application checks alone cannot do this: two requests can both read "seat 2 is
free" before either writes. See the long comment in the `appointments` migration.

### Timezones

Every timestamp is **stored in UTC**. `CLINIC_TIMEZONE` is what patients and
staff see. Booking code never calls `now()` — it calls `App\Support\Clock`. That
one small class is the entire timezone story; read it before touching any date
code.

---

## Payments

### Swapping the gateway

SSLCommerz ships as the worked example, but **nothing in the booking layer has
ever heard of it**. To use a different processor:

1. Write a class implementing `App\Contracts\PaymentGateway` (five methods).
2. Add an entry to `payment.gateways` in `config/booking.php` with `driver`
   pointing at your class. The whole array is passed to your constructor, so put
   your credentials in it.
3. Point `PAYMENT_GATEWAY` at the new key in `.env`.

No booking code changes. There is an architecture test that **fails** if anyone
makes `App\Services\Booking` depend on a specific gateway.

`app/Services/Payments/Gateways/PayAtClinicGateway.php` is worth reading first —
it is a gateway that takes no money at all, and it exists to prove the
abstraction is real rather than shaped around one provider.

### The rule a new gateway must not break

`handleCallback()` **may not believe the request**. A callback URL is public and
its POST body is attacker-controlled — anyone can send a form claiming a payment
succeeded. Every implementation must confirm the transaction with the provider's
own API, and must check the **amount**. Without that check a patient pays one
taka and walks away with a confirmed appointment.

`SslCommerzGateway::handleCallback()` checks four things, all of which must
hold: the provider says VALID, the transaction id is the one we issued, the
amount matches to the paisa, and the currency matches.

### Why payment is not required

`PAYMENTS_REQUIRED` is `false` by default and should usually stay that way. A
patient who abandons a gateway page should still end up with an appointment the
chamber can ring them about, not with nothing. A gateway being down is the
practice's problem, not the patient's.

---

## SMS and WhatsApp

**The template ships no paid gateway**, deliberately — every country has
different providers, prices and sender-id rules.

The default `null` driver writes what it *would* have sent to
`storage/logs/laravel.log`, so a developer can see every message without holding
an account anywhere.

To go live, either:

- set `SMS_DRIVER=http` and fill in the three credentials, which works with most
  bulk-SMS REST APIs — see
  `app/Services/Sms/ExampleHttpSmsSender.php`, a complete working
  implementation with the three provider-specific lines clearly marked; or
- write your own class implementing `App\Contracts\SmsSender` (two methods) and
  register it in `AppServiceProvider`.

WhatsApp needs no separate interface. A class wrapping the WhatsApp Cloud API
satisfies `SmsSender` exactly as an SMS provider would.

---

## Health videos

Three sources, one component. The doctor pastes a **YouTube or Vimeo link** in
any form — watch page, share link, shorts, embed URL, with or without a
timestamp — and it is normalised to a bare id on save. They can also **upload an
MP4**, though a link is strongly preferred: a self-hosted 200 MB file on shared
hosting will be the slowest thing on the site.

Two details worth knowing:

- **Nothing loads until the visitor presses play.** A YouTube iframe pulls
  roughly half a megabyte and sets tracking cookies the moment it renders, so a
  grid of ten videos would cost five megabytes before anybody watched anything.
  The grid shows poster images; the player is created on click.
- Embeds use **youtube-nocookie.com**, so no third-party cookie is set until the
  visitor makes a deliberate choice. That is the right default on a page about
  somebody's illness.

Upload limits are capped at 50 MB in the admin form, but your hosting will
usually allow less. Raising it means increasing `upload_max_filesize` and
`post_max_size` in `php.ini`.

---

## Medical documents

Prescriptions and reports live on a **private `medical` disk**, outside
`public/`, with `serve => false` so Laravel registers no route to them. They
have no URL at all.

The only way to a file is `MedicalDocumentController`, which authorises every
single request through `MedicalDocumentPolicy`. Files on disk are named with a
ULID — a hostile upload filename is never used as a path — and the human name is
restored at download time.

The doctor can upload a document and **hold it back**, releasing it to the
patient once they have reviewed it.

Uploads happen from the appointment screen (where the patient is already in
context) or from **Patients → Documents** for anything arriving later.

---

## Sending email

Configured entirely through `.env`; no code changes are needed to swap
providers. During development `MAIL_MAILER=log` writes messages to
`storage/logs/laravel.log`.

Notifications go to the address on the **Doctor profile** page, falling back to
`MAIL_FROM_ADDRESS`.

**Mail is sent synchronously**, because most buyers run on shared hosting with
no queue worker and a queued mail there would sit unsent forever. If you do run
`php artisan queue:work`, add one interface to any notification and nothing else
changes:

```php
class AppointmentBookedPatient extends Notification implements ShouldQueue
```

A mail failure is logged and **never** breaks a booking — the appointment is
always committed first.

---

## Scheduled tasks

Both are **optional**. The site works correctly without a scheduler.

```
* * * * * cd /path/to/the/site && php artisan schedule:run >> /dev/null 2>&1
```

| Command | What it does |
|---|---|
| `appointments:release-unpaid` | Frees seats held for a payment that never arrived. Also happens lazily on the next booking, so cron is not required. |
| `appointments:send-reminders` | Emails patients the day before. Without cron, reminders are simply never sent. |

---

## Deploying

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan storage:link
php artisan optimize            # caches config, routes and views
php artisan filament:optimize   # caches Filament components and icons
```

Set in production `.env`:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-clinic.com
SSLCOMMERZ_SANDBOX=false
```

Then update `public/robots.txt` with the real address:

```
Sitemap: https://your-clinic.com/sitemap.xml
```

> After deploying, re-run `php artisan optimize` whenever you change anything in
> `config/site.php`, or the cached config will keep serving the old colours.

---

## Project layout

```
app/
├── Contracts/                     PaymentGateway, SmsSender — the two integration points
├── Enums/                         AppointmentStatus, PaymentStatus, BookingActor, …
├── Filament/
│   ├── Pages/DoctorProfileSettings.php    The singleton profile form
│   ├── Resources/                          One folder per admin screen
│   └── Widgets/                            Dashboard cards, chart, today's list
├── Http/Controllers/
│   ├── Patient/                            The patient account area
│   └── …                                   One controller per public page
├── Livewire/                      BookingWizard, VideoLibrary
├── Models/                        Fourteen models, each with scopes
├── Notifications/                 Emails to patient and to chamber
├── Policies/                      Who may see and do what
├── Services/
│   ├── Booking/                   >>> THE CORE. Availability, slots, workflow <<<
│   ├── Payments/                  Gateway abstraction + drivers
│   ├── Notifications/             The one place anything is sent
│   └── Sms/                       The SMS integration point
└── Support/
    ├── Clock.php                  >>> THE ENTIRE TIMEZONE STORY <<<
    ├── Media.php                  Upload path → public URL
    ├── Slot.php                   One bookable time
    └── VideoEmbed.php             YouTube / Vimeo URL parsing

config/site.php                    >>> REBRAND HERE <<< (branding only)
config/booking.php                 Operational settings a developer sets

resources/
├── css/app.css                    Design tokens, scroll reveal, prose styling
├── css/filament/admin/theme.css   The admin panel's visual language
├── js/app.js                      Livewire + Alpine (read the note at the top)
└── views/
    ├── components/layouts/        Page shell, SEO meta, brand CSS variables
    ├── components/ui/             Button, card, badge, video player, …
    ├── components/site/           Navbar, footer, WhatsApp, schema.org
    ├── components/patient/        The patient account chrome
    └── pages/                     One file per page
```

### Conventions worth knowing

- **`$doctor` is available in every view.** `AppServiceProvider` shares it, and
  the model caches itself for the request, so using it costs one query per page
  however many components read it.
- **Query scopes hold the visibility rules.** Always `BlogPost::published()`,
  never a hand-written column check — that is how a draft ends up visible on one
  page and hidden on another.
- **Uploads go through `Media::url()`**, so views never build a path themselves.
- **Never write `$appointment->status` directly.** Every status change goes
  through `AppointmentWorkflow`, which validates the transition, records who did
  it, and sends the right emails. The doc block on that class explains why it is
  not a model observer.
- **Pure normalisation belongs in a model hook; anything with a side effect
  belongs in an explicit service call.** That line is used consistently
  throughout, and both sides of it are commented where they occur.
- **Blog and article content is rendered unescaped** because it comes from the
  site owner's own editor. Never pipe visitor-submitted content through it.

---

## Tests

```bash
php artisan test
```

224 tests covering slot generation and its precedence rules, double-booking
under concurrency, the booking wizard, payment verification (including a
tampered-amount case), patient/staff isolation, document access control, every
admin screen, every public page, and the schema.org output.

They run against an in-memory SQLite database, so they need no setup and never
touch your MySQL data.

Code style is handled by Laravel Pint:

```bash
vendor/bin/pint
```

---

## Licence

Sold as source code. The buyer may use and modify it for their own practice.
