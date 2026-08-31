<?php

use App\Support\Text;

/*
|--------------------------------------------------------------------------
| App\Support\Text
|--------------------------------------------------------------------------
|
| This replaced `nl2br(e($value))` in four views. The escaping half of that is
| the half that matters — these fields are typed into the admin panel and
| printed with {!! !!}, so a bug here is stored XSS on a medical site.
|
*/

it('makes a paragraph of each block separated by a blank line', function () {
    $html = Text::rich("First paragraph.\n\nSecond paragraph.")->toHtml();

    expect($html)->toBe('<p>First paragraph.</p><p>Second paragraph.</p>');
});

it('keeps a single newline as a line break inside one paragraph', function () {
    // Someone typing an address into a textarea means these as line breaks.
    // nl2br keeps the newline alongside the tag, which is correct HTML — the
    // assertion is on the break existing, not on the whitespace around it.
    $html = Text::rich("House 42\nDhanmondi")->toHtml();

    expect($html)->toStartWith('<p>House 42<br>')
        ->and($html)->toEndWith('Dhanmondi</p>')
        ->and(substr_count($html, '<p>'))->toBe(1);
});

it('escapes markup rather than rendering it', function () {
    $html = Text::rich('<script>alert(1)</script> & "quotes"')->toHtml();

    expect($html)->not->toContain('<script>')
        ->and($html)->toContain('&lt;script&gt;')
        ->and($html)->toContain('&amp;');
});

it('treats Windows and old-Mac line endings as line endings', function () {
    // A paste from Word arrives with \r\n. Without normalising, the blank-line
    // test never matches and the whole thing comes out as one paragraph.
    expect(Text::rich("One.\r\n\r\nTwo.")->toHtml())->toBe('<p>One.</p><p>Two.</p>')
        ->and(Text::rich("One.\r\rTwo.")->toHtml())->toBe('<p>One.</p><p>Two.</p>');
});

it('collapses runs of blank lines instead of emitting empty paragraphs', function () {
    $html = Text::rich("One.\n\n\n\n   \n\nTwo.")->toHtml();

    expect($html)->toBe('<p>One.</p><p>Two.</p>');
});

it('returns nothing for empty input', function (?string $value) {
    expect(Text::rich($value)->toHtml())->toBe('');
})->with([null, '', '   ', "\n\n"]);
