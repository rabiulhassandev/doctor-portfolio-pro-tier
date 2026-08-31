<?php

namespace App\Support;

use Illuminate\Support\HtmlString;

/**
 * Turns the plain text the doctor types into safe, well-set HTML.
 *
 * Several admin fields are plain <textarea>s rather than rich-text editors —
 * the biography, the philosophy, an FAQ answer, a video description — because
 * an editor invites markup that then has to be sanitised, and because Google
 * rejects FAQ answers containing markup.
 *
 * Plain text still has to become paragraphs somehow, and the views were each
 * doing `nl2br(e($value))`. That is not the same thing:
 *
 *   * `nl2br` gives <br><br> between paragraphs, so paragraph spacing is
 *     whatever two line boxes happen to measure rather than a typographic
 *     decision, and CSS has nothing to style.
 *   * Nothing carries a real <p>, so the prose styles that set the opening
 *     paragraph larger have nothing to select.
 *
 * So: a blank line starts a new paragraph, a single newline stays a line break
 * within one. That is what someone typing into a textarea means by both.
 */
class Text
{
    /**
     * Escaped, paragraphed HTML — safe to echo with {!! !!}.
     *
     * Every character of input goes through htmlspecialchars first, so the only
     * tags in the result are the <p> and <br> this method puts there itself.
     */
    public static function rich(?string $value): HtmlString
    {
        $value = trim((string) $value);

        if ($value === '') {
            return new HtmlString('');
        }

        // Normalise Windows and old-Mac line endings, or a paste from Word
        // splits on \r\n and the blank-line test never matches.
        $value = str_replace(["\r\n", "\r"], "\n", $value);

        $paragraphs = preg_split('/\n\s*\n+/', $value) ?: [];

        $html = collect($paragraphs)
            ->map(fn (string $paragraph): string => trim($paragraph))
            ->filter()
            ->map(fn (string $paragraph): string => '<p>'.nl2br(e($paragraph), false).'</p>')
            ->implode('');

        return new HtmlString($html);
    }
}
