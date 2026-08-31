<?php

use App\Models\DoctorProfile;

/*
|--------------------------------------------------------------------------
| DoctorProfile::shortName()
|--------------------------------------------------------------------------
|
| How prose on the site refers to the doctor. Five views used to do
| `Str::before($doctor->name, ' ')` for this, which on any name beginning with
| an honorific — which is every name this field will hold — returned "Dr." and
| produced sentences like "Book a consultation and Dr. will work it out with
| you." The pages rendered fine and read as broken.
|
*/

it('gives the honorific and the family name', function () {
    $doctor = new DoctorProfile(['name' => 'Dr. Tahmina Rahman']);

    expect($doctor->shortName())->toBe('Dr. Rahman');
});

it('handles a longer name', function () {
    $doctor = new DoctorProfile(['name' => 'Prof. Nafis Ahmed Chowdhury']);

    expect($doctor->shortName())->toBe('Prof. Chowdhury');
});

it('gives the family name alone when there is no honorific', function () {
    $doctor = new DoctorProfile(['name' => 'Tahmina Rahman']);

    expect($doctor->shortName())->toBe('Rahman');
});

it('never returns a bare title', function (string $name) {
    // The whole point. Anything that would reduce to punctuation or an
    // honorific on its own comes back untouched instead.
    expect((new DoctorProfile(['name' => $name]))->shortName())->toBe($name);
})->with(['Dr.', 'Dr. Prof.']);

it('returns a single-word name unchanged', function () {
    expect((new DoctorProfile(['name' => 'Tahmina']))->shortName())->toBe('Tahmina');
});

it('copes with an empty name', function () {
    expect((new DoctorProfile(['name' => '']))->shortName())->toBe('');
});
