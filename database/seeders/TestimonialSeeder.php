<?php

namespace Database\Seeders;

use App\Models\Testimonial;
use Illuminate\Database\Seeder;

/**
 * Demo testimonials.
 *
 * >>> These patients are invented and these quotes were never said. <<<
 *
 * They ship WITHOUT photographs on purpose. Attaching a stranger's face to a
 * quote they never gave is bad enough on its own; doing it beside a claim about
 * their heart is worse, and most real patients would rather their face were not
 * on a page about their cardiac history anyway. The public card falls back to
 * initials in a circle, which looks deliberate.
 */
class TestimonialSeeder extends Seeder
{
    public function run(): void
    {
        $testimonials = [
            [
                'name' => 'Sharmin A.',
                'role' => 'Patient since 2019',
                'rating' => 5,
                'message' => 'My blood pressure had been high for three years and nobody had explained why the '
                    .'tablets kept changing. Dr. Rahman drew it out on paper for me. It is the first time I '
                    .'have understood my own treatment.',
            ],
            [
                'name' => 'Rezaul K.',
                'role' => 'Patient',
                'rating' => 5,
                'message' => 'I came in convinced I was having a heart attack. She did the ECG straight away, '
                    .'sat me down and told me honestly that it was not my heart. She could have sent me for a '
                    .'dozen tests instead.',
            ],
            [
                'name' => 'Nasreen H.',
                'role' => 'Patient since 2021',
                'rating' => 5,
                'message' => 'The echo was done the same evening and I had the report before I left. Everywhere '
                    .'else I have been, that means coming back next week.',
            ],
            [
                'name' => 'Imran S.',
                'role' => 'Patient',
                'rating' => 4,
                'message' => 'The chamber runs a little late in the evenings, but that is because she does not '
                    .'rush anyone — including me. Worth the wait.',
            ],
            [
                'name' => 'Farhana K.',
                'role' => 'Booking for her father',
                'rating' => 5,
                'message' => 'Booking online meant I could arrange my father\'s appointment from Chattogram '
                    .'without a dozen phone calls. His prescription was in the account the same night.',
            ],
            [
                'name' => 'Tanvir M.',
                'role' => 'Patient since 2018',
                'rating' => 5,
                'message' => 'She told me to lose eight kilos before she would consider changing my medication. '
                    .'It was not what I wanted to hear and it was completely right.',
            ],
        ];

        foreach ($testimonials as $index => $testimonial) {
            Testimonial::query()->updateOrCreate(
                ['name' => $testimonial['name']],
                [...$testimonial, 'is_published' => true, 'sort_order' => $index],
            );
        }
    }
}
