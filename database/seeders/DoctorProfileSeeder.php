<?php

namespace Database\Seeders;

use App\Models\DoctorProfile;
use Illuminate\Database\Seeder;

/**
 * The demo doctor.
 *
 * >>> DR. NAFIS AHMED CHOWDHURY DOES NOT EXIST. <<<
 *
 * The name, the chamber, the BMDC number, the qualifications and the fee are
 * all invented. The telephone number ends in zeroes and the email uses the
 * reserved `.example` domain, so nothing on a seeded demo site can reach a real
 * person. Replace every word of it from the admin panel before the site goes
 * live for a real practice.
 *
 * The content is written for Bangladesh: degrees in the MBBS → BCS → FCPS → MD
 * order, a BMDC registration number, a Dhanmondi chamber, evening hours and
 * Friday closed. To move the template to another market, change this file,
 * DoctorProfile::DAYS (the week order) and the `registration_label` field.
 */
class DoctorProfileSeeder extends Seeder
{
    public function run(): void
    {
        // updateOrCreate so re-running the seeders is always safe.
        DoctorProfile::query()->updateOrCreate(
            ['id' => DoctorProfile::query()->value('id') ?? 1],
            [
                'name' => 'Dr. Nafis Ahmed Chowdhury',
                'specialization' => 'Consultant Cardiologist',
                'registration_label' => 'BMDC Reg. No.',
                'registration_number' => 'A-42817',
                'chamber_name' => 'Sohrid Heart Care',
                'tagline' => 'Careful, unhurried heart care — and the time to explain it properly.',
                'photo' => 'doctor/portrait.jpg',
                'years_of_experience' => 20,

                'short_bio' => 'I have looked after hearts in Dhaka for twenty years. Most of what worries '
                    .'my patients turns out to be manageable — the difficult part is usually getting a '
                    .'straight answer about what is actually happening.',

                'bio' => <<<'TEXT'
                I qualified from Dhaka Medical College in 2004 and have spent the twenty years since in
                cardiology, first at the National Institute of Cardiovascular Diseases and, since 2013, in
                my own chamber in Dhanmondi.

                My work is mostly the unglamorous half of cardiology: blood pressure that will not settle,
                chest pain that may or may not be the heart, breathlessness that has crept up over a year.
                I do echocardiography and ECG here at the chamber, so in most cases you leave the same
                evening knowing what is going on rather than waiting a week for a report.

                I see patients from across Dhaka and, increasingly, from outside it. If you have been sent
                by another doctor, please bring whatever reports you have — even old ones. A trace from
                three years ago often tells me more than a fresh one.
                TEXT,

                'philosophy' => <<<'TEXT'
                A consultation should not feel rushed, and you should leave understanding what is wrong,
                what we are going to do about it, and what would make me want to see you sooner.

                I would rather spend fifteen extra minutes explaining a diagnosis than have a patient go
                home and look it up in a panic. If I have not been clear, please say so and ask again.
                TEXT,

                'qualifications' => [
                    ['title' => 'MD (Cardiology)', 'institution' => 'National Institute of Cardiovascular Diseases', 'year' => '2013'],
                    ['title' => 'FCPS (Medicine)', 'institution' => 'Bangladesh College of Physicians and Surgeons', 'year' => '2010'],
                    ['title' => 'BCS (Health)', 'institution' => 'Bangladesh Civil Service', 'year' => '2006'],
                    ['title' => 'MBBS', 'institution' => 'Dhaka Medical College', 'year' => '2004'],
                ],

                // Reserved documentation values — these cannot reach anybody.
                'email' => 'chamber@example.com',
                'phone' => '+880 1700-000000',
                'whatsapp' => '8801700000000',

                'address_line' => 'House 42 (3rd floor), Road 8, Dhanmondi',
                'city' => 'Dhaka',
                'state' => 'Dhaka Division',
                'postal_code' => '1205',
                'country' => 'Bangladesh',
                'map_latitude' => 23.7461,
                'map_longitude' => 90.3742,

                /*
                 | Published opening hours: when the chamber is open at all.
                 | NOT the same as bookable availability, which is seeded by
                 | AvailabilitySeeder — the chamber opens at six and takes
                 | booked patients from six thirty.
                 */
                'working_hours' => [
                    ['day' => 'saturday', 'opens' => '18:00', 'closes' => '21:00', 'is_closed' => false],
                    ['day' => 'sunday', 'opens' => '18:00', 'closes' => '21:00', 'is_closed' => false],
                    ['day' => 'monday', 'opens' => '18:00', 'closes' => '21:00', 'is_closed' => false],
                    ['day' => 'tuesday', 'opens' => '18:00', 'closes' => '21:00', 'is_closed' => false],
                    ['day' => 'wednesday', 'opens' => '18:00', 'closes' => '21:00', 'is_closed' => false],
                    ['day' => 'thursday', 'opens' => '10:00', 'closes' => '13:00', 'is_closed' => false],
                    ['day' => 'friday', 'opens' => null, 'closes' => null, 'is_closed' => true],
                ],

                'social_links' => [
                    'facebook' => 'https://facebook.com/example',
                    'youtube' => 'https://youtube.com/@example',
                ],

                'consultation_fee' => 1500,
                'booking_horizon_days' => 30,
                'booking_instructions' => 'Please arrive ten minutes early, and bring any previous reports, '
                    .'ECGs or prescriptions — even old ones. If you are coming about blood pressure, bring '
                    .'your home readings if you have been keeping them.',

                'meta_title' => 'Dr. Nafis Ahmed Chowdhury — Consultant Cardiologist, Dhanmondi',
                'meta_description' => 'Consultant cardiologist in Dhanmondi, Dhaka. Echocardiography, ECG '
                    .'and blood pressure care. Book an appointment online and collect your reports.',
            ],
        );
    }
}
