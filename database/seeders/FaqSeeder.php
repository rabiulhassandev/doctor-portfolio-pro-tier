<?php

namespace Database\Seeders;

use App\Models\Faq;
use Illuminate\Database\Seeder;

/**
 * The questions a chamber answers on the phone forty times a week.
 *
 * These are also published as schema.org FAQPage markup, so Google can show
 * the answers directly in search results. Answers are plain text on purpose —
 * Google quietly rejects FAQ markup containing headings and images.
 */
class FaqSeeder extends Seeder
{
    public function run(): void
    {
        $faqs = [
            // --- Appointments -------------------------------------------
            [
                'category' => 'Appointments',
                'question' => 'Do I need an appointment, or can I just come?',
                'answer' => 'Booked patients are seen first, so an appointment is strongly advised — especially '
                    .'in the evenings. If you turn up without one you will be seen if there is a gap, but you may '
                    .'wait an hour or more.',
            ],
            [
                'category' => 'Appointments',
                'question' => 'How far in advance can I book?',
                'answer' => 'Up to a month ahead. The booking page only shows times that are genuinely free, so '
                    .'anything you can select is available.',
            ],
            [
                'category' => 'Appointments',
                'question' => 'Can I change or cancel my appointment?',
                'answer' => 'Yes. Sign in to your account and you can cancel it yourself up until the day before. '
                    .'Closer than that, please telephone the chamber so we can offer the slot to somebody else.',
            ],
            [
                'category' => 'Appointments',
                'question' => 'How long does a consultation take?',
                'answer' => 'A first consultation is about thirty minutes. Follow-ups are usually shorter. The '
                    .'chamber sometimes runs late in the evenings because nobody is rushed out.',
            ],

            // --- Fees ---------------------------------------------------
            [
                'category' => 'Fees and payment',
                'question' => 'What does a consultation cost?',
                'answer' => 'The consultation fee is shown when you book. Tests such as an echocardiogram or ECG '
                    .'are charged separately, and you will be told the cost before anything is done.',
            ],
            [
                'category' => 'Fees and payment',
                'question' => 'Do I have to pay online when I book?',
                'answer' => 'No. You can pay online in advance if it suits you, or simply pay at the chamber on '
                    .'the day. Neither is faster than the other.',
            ],
            [
                'category' => 'Fees and payment',
                'question' => 'Do you accept bKash and Nagad?',
                'answer' => 'Yes, both, along with cards and bank transfer, through the online payment page. Cash '
                    .'is accepted at the chamber.',
            ],

            // --- Your visit ---------------------------------------------
            [
                'category' => 'Your visit',
                'question' => 'What should I bring?',
                'answer' => 'Any previous reports, ECGs, echo results or prescriptions — even old ones. A trace '
                    .'from three years ago is often more useful than a new one, because it shows what has '
                    .'changed. If you take medicines, bring the boxes rather than a list.',
            ],
            [
                'category' => 'Your visit',
                'question' => 'Do I need to fast before an echocardiogram?',
                'answer' => 'No. An echo needs no preparation at all. You may eat and drink normally and take '
                    .'your usual medicines.',
            ],
            [
                'category' => 'Your visit',
                'question' => 'When will I get my report?',
                'answer' => 'Echo and ECG reports are usually ready the same evening, and appear in your patient '
                    .'account as soon as they are issued. You will get an email when one is ready.',
            ],
            [
                'category' => 'Your visit',
                'question' => 'Can I bring somebody with me?',
                'answer' => 'Please do. A second person remembers half of what was said, which is genuinely '
                    .'useful when there is a lot to take in.',
            ],
        ];

        foreach ($faqs as $index => $faq) {
            Faq::query()->updateOrCreate(
                ['question' => $faq['question']],
                [...$faq, 'is_published' => true, 'sort_order' => $index],
            );
        }
    }
}
