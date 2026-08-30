<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * The demo services.
 *
 * Written as things a patient would recognise rather than as procedure names —
 * "blood pressure that will not settle" finds more people than "resistant
 * hypertension management" ever will.
 */
class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        $services = [
            [
                'title' => 'Heart consultation',
                'icon' => 'heroicon-o-heart',
                'summary' => 'A full assessment of your heart, with time to talk through what is worrying you.',
                'description' => 'A first consultation takes about half an hour. We go through your symptoms, '
                    .'your history and any reports you bring, and I examine you properly. Most people leave with '
                    .'either a clear answer or a short list of tests that will get us one.',
                'is_featured' => true,
            ],
            [
                'title' => 'Echocardiography',
                'icon' => 'heroicon-o-beaker',
                'summary' => 'An ultrasound of the heart, done here at the chamber with the report the same evening.',
                'description' => 'An echo shows how the heart muscle and valves are actually working. It is '
                    .'painless, takes about twenty minutes, and needs no preparation. Because the machine is here, '
                    .'you do not have to go elsewhere and come back with a report.',
                'is_featured' => true,
            ],
            [
                'title' => 'ECG',
                'icon' => 'heroicon-o-bolt',
                'summary' => 'A trace of the heart\'s electrical rhythm, read on the spot.',
                'description' => 'Five minutes, no discomfort, and often the quickest way to settle whether chest '
                    .'pain or palpitations need worrying about.',
                'is_featured' => true,
            ],
            [
                'title' => 'Blood pressure management',
                'icon' => 'heroicon-o-chart-bar',
                'summary' => 'For blood pressure that will not come down, or medicines that do not suit you.',
                'description' => 'High blood pressure is rarely a single decision — it is a series of small '
                    .'adjustments over months. Bring your home readings if you keep them; they tell me far more '
                    .'than one measurement taken in a chamber where you are already nervous.',
                'is_featured' => true,
            ],
            [
                'title' => 'Cholesterol and diabetes care',
                'icon' => 'heroicon-o-shield-check',
                'summary' => 'Managing the things that quietly damage the heart over years.',
                'description' => 'Neither has symptoms until it is late, which is exactly why they are worth '
                    .'watching. We look at your numbers together and agree what is realistic to change.',
                'is_featured' => false,
            ],
            [
                'title' => 'Pre-operative heart assessment',
                'icon' => 'heroicon-o-clipboard-document-check',
                'summary' => 'Clearance before surgery, when a surgeon or anaesthetist has asked for it.',
                'description' => 'A focused assessment of whether your heart is fit for the operation you are '
                    .'having, with a written report for your surgical team.',
                'is_featured' => false,
            ],
            [
                'title' => 'Follow-up and long-term care',
                'icon' => 'heroicon-o-clock',
                'summary' => 'Regular review for anyone already under treatment.',
                'description' => 'Most heart conditions are managed rather than cured. Coming back at sensible '
                    .'intervals is what keeps a manageable problem manageable.',
                'is_featured' => false,
            ],
            [
                'title' => 'Second opinion',
                'icon' => 'heroicon-o-academic-cap',
                'summary' => 'A fresh look at a diagnosis or a proposed procedure.',
                'description' => 'Nobody should feel awkward asking for one. Bring everything you have and I will '
                    .'tell you honestly whether I would do the same.',
                'is_featured' => false,
            ],
        ];

        foreach ($services as $index => $service) {
            Service::query()->updateOrCreate(
                ['slug' => Str::slug($service['title'])],
                [...$service, 'is_published' => true, 'sort_order' => $index],
            );
        }
    }
}
