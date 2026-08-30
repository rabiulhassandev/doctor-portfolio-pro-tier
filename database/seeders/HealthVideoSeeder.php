<?php

namespace Database\Seeders;

use App\Enums\VideoType;
use App\Models\HealthVideo;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * The demo video library.
 *
 * A deliberate mix of sources, so a buyer can see how each behaves before
 * uploading anything of their own:
 *
 *   * YouTube links, which is the recommended route and what most doctors will
 *     actually use;
 *   * one Vimeo link, to show the unlisted-hash handling;
 *   * one "uploaded" entry, which demonstrates the self-hosted path.
 *
 * >>> The YouTube and Vimeo ids below are real, publicly available videos used
 * >>> purely so the demo has something that plays. They are not medical advice
 * >>> and have nothing to do with any practice. Replace every one of them.
 *
 * The uploaded entry deliberately points at a file that does not exist. That
 * demonstrates the graceful fallback — a branded gradient placeholder rather
 * than a broken player — without committing a large binary to the repository.
 */
class HealthVideoSeeder extends Seeder
{
    public function run(): void
    {
        $videos = [
            [
                'title' => 'What high blood pressure actually does',
                'topic' => 'Blood pressure',
                'description' => 'Blood pressure has no symptoms until it has already done damage, which is why '
                    ."it gets ignored for years.\n\nThis explains what the two numbers mean, why the top one "
                    .'matters more as you get older, and what happens to the arteries when it stays high.',
                'video_type' => VideoType::Youtube,
                'source_url' => 'https://www.youtube.com/watch?v=BWfeR9Rlp5Y',
                'duration_seconds' => 245,
                'is_featured' => true,
            ],
            [
                'title' => 'Taking your blood pressure properly at home',
                'topic' => 'Blood pressure',
                'description' => 'Most home readings are wrong, and usually in the same direction. Sitting for '
                    .'five minutes first, feet on the floor, cuff at heart height, and not straight after tea — '
                    .'these change the number more than people expect.',
                'video_type' => VideoType::Youtube,
                'source_url' => 'https://youtu.be/aVvV2q_j1SE',
                'duration_seconds' => 190,
                'is_featured' => false,
            ],
            [
                'title' => 'What happens during an echocardiogram',
                'topic' => 'Tests and procedures',
                'description' => 'An echo is an ultrasound of the heart. It is painless, takes about twenty '
                    .'minutes, and needs no preparation at all. This shows what the room looks like and what you '
                    .'will be asked to do, so nothing on the day is a surprise.',
                'video_type' => VideoType::Youtube,
                'source_url' => 'https://www.youtube.com/watch?v=gGGXbGnCEXo',
                'duration_seconds' => 312,
                'is_featured' => true,
            ],
            [
                'title' => 'Understanding heart failure',
                'topic' => 'Heart failure',
                'description' => 'The name frightens people more than the condition warrants. Heart failure does '
                    .'not mean the heart is about to stop — it means it is not pumping as efficiently as it '
                    ."should.\n\nMost people manage it for many years with medication and sensible adjustments.",
                'video_type' => VideoType::Youtube,
                'source_url' => 'https://www.youtube.com/watch?v=Vc9WCXQNGrY',
                'duration_seconds' => 420,
                'is_featured' => true,
            ],
            [
                'title' => 'Chest pain: when to worry',
                'topic' => 'Symptoms',
                'description' => 'Not all chest pain is the heart, and not all heart pain is dramatic. This covers '
                    .'the patterns that need attention today, the ones that can wait for an appointment, and the '
                    .'ones that are almost never cardiac.',
                'video_type' => VideoType::Youtube,
                'source_url' => 'https://www.youtube.com/watch?v=gDwt7dD3awc',
                'duration_seconds' => 268,
                'is_featured' => false,
            ],
            [
                'title' => 'Living with diabetes and a heart condition',
                'topic' => 'Living with diabetes',
                'description' => 'Diabetes roughly doubles cardiac risk, and the two conditions are usually '
                    .'managed by different doctors. This is about keeping the two plans from working against each '
                    .'other.',
                // A Vimeo link, to demonstrate the second embed provider.
                'video_type' => VideoType::Vimeo,
                'source_url' => 'https://vimeo.com/76979871',
                'duration_seconds' => 355,
                'is_featured' => false,
            ],
            [
                'title' => 'After your angiogram: the first week',
                'topic' => 'After your angiogram',
                'description' => 'What the wrist or groin site should look like, when the bruising is normal, '
                    .'when to ring, and when you can drive again.',
                /*
                 | An "uploaded" video whose file is not in the repository —
                 | deliberately. It shows how a self-hosted entry is configured
                 | and proves the branded fallback works, without committing a
                 | large binary. Upload a real file from the admin panel to see
                 | the player.
                 */
                'video_type' => VideoType::Upload,
                'video_path' => 'videos/after-your-angiogram.mp4',
                'duration_seconds' => 205,
                'is_featured' => false,
            ],
        ];

        foreach ($videos as $index => $video) {
            HealthVideo::query()->updateOrCreate(
                ['slug' => Str::slug($video['title'])],
                [
                    ...$video,
                    'is_published' => true,
                    'published_at' => now()->subDays(60 - ($index * 7)),
                    'sort_order' => $index,
                ],
            );
        }
    }
}
