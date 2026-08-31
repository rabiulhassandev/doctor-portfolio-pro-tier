<?php

namespace Database\Seeders;

use App\Models\SeoPage;
use App\Models\SeoSetting;
use Illuminate\Database\Seeder;

/**
 * Search settings for the demo, and a worked example of what good looks like.
 *
 * Two jobs. It gives a fresh install values that work rather than an empty
 * settings screen — and it shows a buyer what a filled-in page description
 * actually reads like, which is far more useful than an empty box under a
 * helper text telling them to write one.
 *
 * Every description below is written the way the admin screen asks for: aimed
 * at a person deciding whether to click, roughly 150 characters, and naming the
 * thing somebody would actually have typed into Google — a condition, a
 * procedure, a neighbourhood.
 *
 * >>> REWRITE ALL OF IT for a real practice. <<<
 * These mention Dhanmondi, a cardiologist and a fictional name. Left as they
 * are, they describe somebody who does not exist.
 */
class SeoSeeder extends Seeder
{
    public function run(): void
    {
        SeoSetting::query()->updateOrCreate(
            ['id' => SeoSetting::query()->value('id') ?? 1],
            [
                'title_template' => ':page | :site',

                'default_meta_description' => 'Consultant cardiologist in Dhanmondi, Dhaka. Echocardiography, ECG '
                    .'and blood pressure care, with time to explain what is happening. Book online.',

                /*
                 | Everything allowed, including the training crawlers.
                 |
                 | A demo has to show the switches working, and a template that
                 | shipped with them off would quietly make every buyer
                 | invisible to AI search unless they found this screen. The
                 | training ones are a genuine choice and the admin form says
                 | so — this is only the starting position.
                 */
                'ai_crawlers' => collect(SeoSetting::AI_CRAWLERS)->map(fn (): bool => true)->all(),

                'price_range' => '৳৳',
                'languages' => ['Bengali', 'English'],
                'areas_served' => ['Dhanmondi', 'Dhaka', 'Bangladesh'],
                'payment_accepted' => ['Cash', 'bKash', 'Card', 'Online'],

                // Deliberately empty: verification codes and analytics IDs belong
                // to a real domain and a real account, and a demo has neither.
                'discourage_indexing' => false,
            ],
        );

        $descriptions = [
            'home' => 'Dr. Tahmina Rahman, consultant cardiologist in Dhanmondi, Dhaka. Eighteen years '
                .'of heart care, echocardiography and ECG at the chamber, and appointments you can book online.',

            'about' => 'Eighteen years in cardiology, at the National Institute of Cardiovascular Diseases '
                .'and in Dhanmondi since 2016. Qualifications, approach, and what to expect from a consultation.',

            'services' => 'Heart consultations, echocardiography, ECG, blood pressure and cholesterol care, '
                .'and pre-operative assessment — all at the chamber in Dhanmondi, most with the report the same evening.',

            'booking' => 'Choose a time that suits you from the appointments actually free at the chamber. '
                .'Confirmed by email in a minute, with the option to pay online or at the clinic.',

            'contact' => 'The chamber is at House 42, Road 8, Dhanmondi, Dhaka. Opening hours, telephone '
                .'number and directions, plus WhatsApp for anything quick.',

            'blog.index' => 'Plain-language articles on blood pressure, chest pain, echocardiogram reports '
                .'and living with a heart condition — written for patients rather than for colleagues.',

            'videos.index' => 'Short films explaining common heart conditions, what the tests involve and '
                .'what happens afterwards. Watch before your appointment, or after it.',

            'faq' => 'Do you need an appointment, what a consultation costs, how long it takes, whether you '
                .'can change a booking — the questions the chamber answers on the telephone most often.',

            'gallery' => 'Photographs of the chamber in Dhanmondi: the waiting area, the consulting room '
                .'and the echocardiography equipment, so you know where you are going before you arrive.',
        ];

        foreach ($descriptions as $routeName => $description) {
            // Only for pages a buyer has left switched on, so a practice with
            // no blog does not get a settings row for one.
            if (! array_key_exists($routeName, SeoPage::availablePages())) {
                continue;
            }

            SeoPage::query()->updateOrCreate(
                ['route_name' => $routeName],
                ['description' => $description],
            );
        }
    }
}
