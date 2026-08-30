{{--
    schema.org Physician / MedicalBusiness markup.

    This is what puts the practice into Google's local results with an address,
    opening hours and a telephone number attached — for a single-doctor chamber
    that panel is worth more than any amount of on-page copy.

    Included on the home, about and contact pages: the three that describe the
    practice itself. Repeating it on every article would not help and risks
    Google treating the site as having many businesses on it.
--}}

@php
    use App\Support\Media;

    // schema.org wants two-letter day names in its own vocabulary.
    $dayNames = [
        'monday' => 'Monday', 'tuesday' => 'Tuesday', 'wednesday' => 'Wednesday',
        'thursday' => 'Thursday', 'friday' => 'Friday', 'saturday' => 'Saturday',
        'sunday' => 'Sunday',
    ];

    $hours = $doctor->scheduleRows()
        ->reject(fn (array $row): bool => $row['is_closed'] || blank($row['opens']) || blank($row['closes']))
        ->map(fn (array $row): array => [
            '@type' => 'OpeningHoursSpecification',
            'dayOfWeek' => 'https://schema.org/'.($dayNames[$row['day']] ?? ucfirst($row['day'])),
            'opens' => substr($row['opens'], 0, 5),
            'closes' => substr($row['closes'], 0, 5),
        ])
        ->values()
        ->all();

    $schema = array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'Physician',
        'name' => $doctor->chamber_name ?: $doctor->name,
        'alternateName' => $doctor->chamber_name ? $doctor->name : null,
        'medicalSpecialty' => $doctor->specialization,
        'description' => $doctor->short_bio ?: config('site.meta_description'),
        'url' => url('/'),
        'image' => Media::absoluteUrl($doctor->photo),
        'telephone' => $doctor->phone,
        'email' => $doctor->email,

        'address' => array_filter([
            '@type' => 'PostalAddress',
            'streetAddress' => $doctor->address_line,
            'addressLocality' => $doctor->city,
            'addressRegion' => $doctor->state,
            'postalCode' => $doctor->postal_code,
            'addressCountry' => $doctor->country,
        ]) ?: null,

        'geo' => ($doctor->map_latitude && $doctor->map_longitude) ? [
            '@type' => 'GeoCoordinates',
            'latitude' => $doctor->map_latitude,
            'longitude' => $doctor->map_longitude,
        ] : null,

        'openingHoursSpecification' => $hours ?: null,

        'sameAs' => $doctor->activeSocialLinks()->values()->all() ?: null,

        // The registration number, expressed the way schema.org understands it.
        'identifier' => $doctor->registration_number ? [
            '@type' => 'PropertyValue',
            'name' => $doctor->registration_label ?: 'Registration number',
            'value' => $doctor->registration_number,
        ] : null,

        'priceRange' => $doctor->hasFee()
            ? config('booking.payment.currency', 'BDT').' '.number_format((float) $doctor->consultation_fee, 0)
            : null,
    ], fn ($value): bool => $value !== null && $value !== '');
@endphp

@push('schema')
    <script type="application/ld+json">
        {!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
    </script>
@endpush
