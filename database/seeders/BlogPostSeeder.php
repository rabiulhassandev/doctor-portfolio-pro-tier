<?php

namespace Database\Seeders;

use App\Models\BlogPost;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Demo articles.
 *
 * >>> Written as demonstration copy, not as medical guidance. Replace them. <<<
 *
 * They are real prose rather than lorem ipsum because a blog full of Latin is
 * the single most obvious sign of an unfinished site, and because a buyer needs
 * to see how the typography behaves with actual sentences in it.
 */
class BlogPostSeeder extends Seeder
{
    public function run(): void
    {
        $posts = [
            [
                'title' => 'Five signs your heart is asking for attention',
                'cover_image' => 'blog/heart-symptoms.jpg',
                'excerpt' => 'Most heart trouble announces itself quietly, in ways that are easy to explain away. '
                    .'Here are the five that are worth an appointment.',
                'content' => <<<'HTML'
                <p>Almost nobody arrives at a cardiologist because of dramatic chest pain. Far more often it is
                something small that has been going on for months — a flight of stairs that has become harder, a
                swelling in the ankles that comes and goes, a heartbeat that occasionally trips over itself.</p>

                <h2>1. Breathlessness that has crept up</h2>
                <p>Not being out of breath after running for a bus, but noticing that the stairs you have climbed
                for twenty years now need a pause halfway. The change matters more than the absolute level. If you
                are doing less than you were a year ago and you cannot say why, that is worth investigating.</p>

                <h2>2. Ankles that swell by the evening</h2>
                <p>Fluid gathering in the ankles during the day and settling overnight is a classic pattern. It has
                many causes, several of them harmless, but it is one of the few things you can genuinely see.</p>

                <h2>3. Palpitations that last</h2>
                <p>Everyone's heart skips occasionally. A racing or irregular beat lasting several minutes,
                especially with dizziness, is different — and worth recording if your phone or watch can do it.</p>

                <h2>4. Chest discomfort brought on by effort</h2>
                <p>The pattern is the informative part. Pain that reliably appears on exertion and settles within
                minutes of stopping is a different animal from pain that comes at rest, or that is worse when you
                press on the spot.</p>

                <h2>5. Waking at night short of breath</h2>
                <p>Having to sit up, or needing an extra pillow you did not need last year, is a symptom people
                rarely mention because it sounds trivial. It is not.</p>

                <p>None of these means something is seriously wrong. All of them mean it is worth half an hour and
                an ECG to find out.</p>
                HTML,
            ],
            [
                'title' => 'Blood pressure: what the two numbers actually mean',
                'cover_image' => 'blog/blood-pressure.jpg',
                'excerpt' => 'Everyone knows their blood pressure reading. Far fewer know what it is describing, '
                    .'or why the top number starts to matter more with age.',
                'content' => <<<'HTML'
                <p>A blood pressure reading is two measurements of the same thing: the pressure inside your
                arteries, taken at the two moments of the heartbeat when it is highest and lowest.</p>

                <h2>The top number</h2>
                <p>Systolic pressure — the push as the heart contracts. It rises naturally with age as the large
                arteries stiffen, and above about fifty it is the number that predicts trouble most reliably.</p>

                <h2>The bottom number</h2>
                <p>Diastolic pressure — the pressure remaining while the heart refills. In younger adults it is
                often the more informative of the two.</p>

                <h2>Why one reading tells you very little</h2>
                <p>Blood pressure varies through the day by more than most people imagine, and a chamber is
                precisely the sort of place that raises it. This is why I ask patients to measure at home, sitting
                quietly, twice a day for a week. Fourteen readings taken calmly are worth far more than one taken
                after a rickshaw ride and a flight of stairs.</p>

                <h2>What we do about it</h2>
                <p>Rarely one decision. Usually a series of small adjustments over several months, checking after
                each. If a medicine does not suit you, say so — there are many, and being quietly miserable on the
                first one we tried helps nobody.</p>
                HTML,
            ],
            [
                'title' => 'Making sense of your echocardiogram report',
                'cover_image' => 'blog/echo-report.jpg',
                'excerpt' => 'Echo reports are written for doctors, which makes them alarming to read at home. '
                    .'Here is what the main terms mean.',
                'content' => <<<'HTML'
                <p>An echocardiogram is an ultrasound of the heart. The report that comes back is dense with
                abbreviations, and patients frequently go home and look up a phrase that turns out to mean
                nothing much at all.</p>

                <h2>Ejection fraction (EF)</h2>
                <p>The proportion of blood the main pumping chamber pushes out with each beat. Normal is roughly
                55 to 70 per cent. It is worth knowing that a healthy heart never empties completely — an EF of
                60 does not mean anything is 40 per cent wrong.</p>

                <h2>"Trivial" or "mild" regurgitation</h2>
                <p>A small amount of backwards flow through a valve. On a modern machine this is found in a great
                many entirely normal hearts. Read on its own it sounds serious; in context it usually is not.</p>

                <h2>Diastolic dysfunction</h2>
                <p>The heart filling less easily than it should, often simply because it has stiffened with age or
                with years of high blood pressure. Graded one to three; grade one is very common.</p>

                <h2>Chamber dimensions</h2>
                <p>Measurements of the four chambers. What matters is not any single figure but whether they have
                changed since last time — which is exactly why I ask you to bring old reports.</p>

                <p>If a phrase in your report worries you, bring it to your next appointment rather than searching
                for it. Almost every term above has a frightening entry somewhere online and a boring explanation
                in the room.</p>
                HTML,
            ],
        ];

        foreach ($posts as $index => $post) {
            BlogPost::query()->updateOrCreate(
                ['slug' => Str::slug($post['title'])],
                [
                    ...$post,
                    'is_published' => true,
                    'published_at' => now()->subDays(45 - ($index * 14)),
                ],
            );
        }
    }
}
