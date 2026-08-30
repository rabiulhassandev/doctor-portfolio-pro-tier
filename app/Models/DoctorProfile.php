<?php

namespace App\Models;

use App\Support\Clock;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Singleton model holding every piece of doctor-specific content.
 *
 * There is only ever one row. Read it with {@see DoctorProfile::current()},
 * which caches the instance for the lifetime of the request so a page that
 * renders the navbar, footer and hero doesn't hit the database three times.
 *
 * @property string $name
 * @property string $specialization
 * @property string|null $registration_label
 * @property string|null $registration_number
 * @property string|null $chamber_name
 * @property string|null $tagline
 * @property string|null $photo
 * @property int|null $years_of_experience
 * @property string|null $short_bio
 * @property string|null $bio
 * @property string|null $philosophy
 * @property array|null $qualifications
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $whatsapp
 * @property string|null $address_line
 * @property string|null $city
 * @property string|null $state
 * @property string|null $postal_code
 * @property string|null $country
 * @property float|null $map_latitude
 * @property float|null $map_longitude
 * @property string|null $map_embed_url
 * @property array|null $working_hours
 * @property array|null $social_links
 * @property float|null $consultation_fee
 * @property int|null $booking_horizon_days
 * @property string|null $booking_instructions
 * @property string|null $meta_title
 * @property string|null $meta_description
 */
class DoctorProfile extends Model
{
    /** Container key under which the current profile is cached for the request. */
    private const CONTAINER_KEY = 'doctor.profile.current';

    /**
     * Weekday keys, in display order. Used by the admin form and the hours table.
     *
     * Saturday first, because the working week in Bangladesh runs Saturday to
     * Thursday with Friday as the day off — a Monday-first table asks a local
     * reader to hunt for today. Reorder this array for another market and the
     * admin form, the hours table and the footer all follow.
     */
    public const DAYS = [
        'saturday' => 'Saturday',
        'sunday' => 'Sunday',
        'monday' => 'Monday',
        'tuesday' => 'Tuesday',
        'wednesday' => 'Wednesday',
        'thursday' => 'Thursday',
        'friday' => 'Friday',
    ];

    /** Mass assignment is safe here: the only writer is the admin settings page. */
    protected $guarded = [];

    /**
     * Saving the profile invalidates the cached copy, so the very next read
     * picks up the new details. Registering it here rather than in the admin
     * page means no future caller can forget to do it.
     */
    protected static function booted(): void
    {
        static::saved(fn () => static::forgetCurrent());
        static::deleted(fn () => static::forgetCurrent());
    }

    protected function casts(): array
    {
        return [
            'qualifications' => 'array',
            'working_hours' => 'array',
            'social_links' => 'array',
            'years_of_experience' => 'integer',
            'booking_horizon_days' => 'integer',
            'consultation_fee' => 'decimal:2',
            'map_latitude' => 'float',
            'map_longitude' => 'float',
        ];
    }

    /**
     * The one and only profile row.
     *
     * The result is cached in the service container for the lifetime of the
     * request, so a page that renders the navbar, hero and footer costs one
     * query rather than three. A container binding is used rather than a static
     * property so the cache cannot survive into the next request — which is
     * what would happen under Octane, or between tests.
     *
     * If the table is empty (fresh install, before seeding) this returns an
     * unsaved model populated from config/site.php, so the public pages still
     * render instead of blowing up on null.
     */
    public static function current(): self
    {
        if (! app()->bound(self::CONTAINER_KEY)) {
            app()->scoped(self::CONTAINER_KEY, fn () => static::query()->first() ?? new static([
                'name' => config('site.name'),
                'specialization' => config('site.specialization'),
                'working_hours' => static::defaultWorkingHours(),
            ]));
        }

        return app(self::CONTAINER_KEY);
    }

    /**
     * Drop the cached copy so the next read hits the database.
     *
     * Called automatically whenever the profile is saved; see booted() above.
     */
    public static function forgetCurrent(): void
    {
        app()->forgetInstance(self::CONTAINER_KEY);
    }

    /** A sensible evening-chamber schedule used as the form default on a fresh install. */
    public static function defaultWorkingHours(): array
    {
        return collect(self::DAYS)
            ->keys()
            ->map(fn (string $key) => [
                'day' => $key,
                'opens' => '18:00',
                'closes' => '21:00',
                'is_closed' => $key === 'friday',
            ])
            ->all();
    }

    /**
     * Working hours normalised to one entry per weekday, in the order given by
     * self::DAYS, so the public hours table never has gaps even if the stored
     * JSON is incomplete.
     *
     * @return Collection<int, array{day: string, label: string, opens: ?string, closes: ?string, is_closed: bool, is_today: bool}>
     */
    public function scheduleRows(): Collection
    {
        $saved = collect($this->working_hours ?? [])->keyBy('day');
        $today = strtolower(Clock::now()->format('l'));

        return collect(self::DAYS)->map(function (string $label, string $key) use ($saved, $today) {
            $row = $saved->get($key, []);

            return [
                'day' => $key,
                'label' => $label,
                'opens' => $row['opens'] ?? null,
                'closes' => $row['closes'] ?? null,
                'is_closed' => (bool) ($row['is_closed'] ?? true),
                'is_today' => $key === $today,
            ];
        })->values();
    }

    /**
     * Whether the clinic is open right now, and the next thing to happen.
     *
     * Patients arriving on the site want one answer before anything else: can I
     * ring them now? The opening-hours table answers it eventually; this answers
     * it at a glance, and is what the pill in the hero and the footer show.
     *
     *     ['is_open' => true,  'label' => 'Open now',   'detail' => 'Closes 9:00 PM']
     *     ['is_open' => false, 'label' => 'Closed now', 'detail' => 'Opens tomorrow at 6:00 PM']
     *
     * Returns null when no day of the week has hours on it — a practice that
     * only sees patients by arrangement is better off saying nothing than
     * announcing that it is permanently closed.
     *
     * @return array{is_open: bool, label: string, detail: string}|null
     */
    public function openStatus(): ?array
    {
        $rows = $this->scheduleRows()->keyBy('day');
        $now = Clock::now();

        // A day counts as open only if it is switched on and has both times.
        $isOpenDay = fn (array $row): bool => ! ($row['is_closed'] ?? true)
            && filled($row['opens'] ?? null)
            && filled($row['closes'] ?? null);

        // Today first: either the clinic is open, or it opens later on.
        $today = $rows->get(strtolower($now->format('l')), []);

        if ($isOpenDay($today)) {
            $opens = $now->setTimeFrom(Carbon::parse($today['opens']));
            $closes = $now->setTimeFrom(Carbon::parse($today['closes']));

            if ($now->between($opens, $closes)) {
                return [
                    'is_open' => true,
                    'label' => 'Open now',
                    'detail' => 'Closes '.$closes->format('g:i A'),
                ];
            }

            if ($now->lt($opens)) {
                return [
                    'is_open' => false,
                    'label' => 'Closed now',
                    'detail' => 'Opens today at '.$opens->format('g:i A'),
                ];
            }
        }

        /*
         | Otherwise walk forward for the next day that has hours on it. Six
         | steps rather than seven: coming back round to today would mean the
         | clinic has already shut for the day, and "opens Tuesday" reads better
         | than "opens today" on a Tuesday evening.
         */
        for ($offset = 1; $offset <= 6; $offset++) {
            $day = $now->addDays($offset);
            $row = $rows->get(strtolower($day->format('l')), []);

            if (! $isOpenDay($row)) {
                continue;
            }

            return [
                'is_open' => false,
                'label' => 'Closed now',
                'detail' => sprintf(
                    'Opens %s at %s',
                    $offset === 1 ? 'tomorrow' : $day->format('l'),
                    Carbon::parse($row['opens'])->format('g:i A'),
                ),
            ];
        }

        return null;
    }

    /**
     * "BMDC Reg. No. A-42817", or null when the practice does not publish one.
     *
     * The label is stored alongside the number rather than hard-coded, because
     * the register a doctor is on depends entirely on where they practise.
     */
    public function registration(): ?string
    {
        if (blank($this->registration_number)) {
            return null;
        }

        return trim(($this->registration_label ?? '').' '.$this->registration_number);
    }

    /** Street address on one line, e.g. "12 Harley St, London, NW1 4LT, UK". */
    public function fullAddress(): string
    {
        return collect([
            $this->address_line,
            $this->city,
            $this->state,
            $this->postal_code,
            $this->country,
        ])->filter()->implode(', ');
    }

    /** Social profile URLs that were actually filled in, keyed by network. */
    public function activeSocialLinks(): Collection
    {
        return collect($this->social_links ?? [])->filter(fn ($url) => filled($url));
    }

    /** `tel:` href with spaces and punctuation stripped. */
    public function telHref(): ?string
    {
        return filled($this->phone)
            ? 'tel:'.preg_replace('/[^0-9+]/', '', $this->phone)
            : null;
    }

    /** Pre-filled WhatsApp deep link for the floating chat button. */
    public function whatsappHref(): ?string
    {
        if (blank($this->whatsapp)) {
            return null;
        }

        $number = preg_replace('/[^0-9]/', '', $this->whatsapp);
        $message = rawurlencode(config('site.whatsapp_message'));

        return "https://wa.me/{$number}?text={$message}";
    }

    /**
     * Where appointment notifications go.
     *
     * Falls back to the address mail is sent *from*, which is always
     * configured — better a copy in the site's own inbox than an email that
     * silently goes nowhere because the profile was left half-filled.
     */
    public function notificationEmail(): ?string
    {
        return $this->email ?: config('mail.from.address');
    }

    /** True when a fee is set and worth showing. */
    public function hasFee(): bool
    {
        return $this->consultation_fee !== null && (float) $this->consultation_fee > 0;
    }
}
