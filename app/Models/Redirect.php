<?php

namespace App\Models;

use App\Http\Middleware\HandleRedirects;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * One managed redirect from an old URL to a live one.
 *
 * @property string $from_path
 * @property string $to_path
 * @property int $status_code
 * @property bool $is_active
 * @property int $hits
 * @property Carbon|null $last_hit_at
 * @property string|null $note
 *
 * @see HandleRedirects
 */
class Redirect extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'status_code' => 'integer',
            'hits' => 'integer',
            'last_hit_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        /*
         | Normalisation, in a model hook because it is PURE — it derives one
         | field from itself and has no side effect. Anything with a
         | consequence belongs in an explicit service call instead; that line
         | is drawn the same way everywhere in this codebase.
         |
         | Without it, a doctor pasting the URL straight out of their browser
         | stores "https://old-site.com/services/" and the middleware — which
         | matches on "/services" — never fires. The rule looks perfect in the
         | table and does nothing, which is the worst kind of broken.
         */
        static::saving(function (self $redirect): void {
            $redirect->from_path = static::normalisePath($redirect->from_path);

            // The target is left alone when it points off-site; only local
            // paths get the same treatment.
            $redirect->to_path = Str::startsWith($redirect->to_path, ['http://', 'https://'])
                ? trim($redirect->to_path)
                : static::normalisePath($redirect->to_path);
        });
    }

    /**
     * "https://old.example.com/services/?utm_source=x" → "/services".
     *
     * The query string is dropped deliberately. Matching on it would mean a
     * rule for every campaign parameter ever appended to the old URL, and the
     * middleware compares paths for exactly that reason.
     */
    public static function normalisePath(?string $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '/';
        }

        // A whole URL was pasted: keep only the path.
        if (Str::startsWith($value, ['http://', 'https://'])) {
            $value = (string) parse_url($value, PHP_URL_PATH);
        }

        $value = Str::before($value, '?');
        $value = Str::before($value, '#');
        $value = '/'.trim($value, '/');

        return $value === '//' ? '/' : $value;
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * The rule for a path, or null.
     *
     * Case-insensitive on purpose: an old site that served /Services and a
     * doctor who types /services should not be two different answers, and
     * MySQL's default collation would treat them as one anyway while SQLite
     * would not. Doing it in PHP makes the behaviour the same on both.
     */
    public static function match(string $path): ?self
    {
        $path = static::normalisePath($path);

        return static::query()
            ->active()
            ->get()
            ->first(fn (self $redirect): bool => Str::lower($redirect->from_path) === Str::lower($path));
    }

    /**
     * Record that this rule fired.
     *
     * Deliberately not a full request log. One counter and one timestamp
     * answer the only question anyone actually asks of a redirect — "is this
     * still doing anything?" — without a table that grows forever on shared
     * hosting.
     */
    public function recordHit(): void
    {
        $this->forceFill([
            'hits' => $this->hits + 1,
            'last_hit_at' => now(),
        ])->saveQuietly();
    }

    /** Where this rule sends a visitor, as a URL. */
    public function target(): string
    {
        return Str::startsWith($this->to_path, ['http://', 'https://'])
            ? $this->to_path
            : url($this->to_path);
    }
}
