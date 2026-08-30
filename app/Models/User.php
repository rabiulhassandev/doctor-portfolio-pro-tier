<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

/**
 * A staff account: the doctor, and anyone else who works the admin panel.
 *
 * This is NOT the patient table. Patients live in `patients` and authenticate
 * on their own guard — see App\Models\Patient. Keeping the two apart means a
 * patient registration can never, by any bug, produce a row in the table that
 * grants access to /admin.
 *
 * @property string $name
 * @property string $email
 * @property string $password
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $user): void {
            if (filled($user->email)) {
                $user->email = Str::lower(trim($user->email));
            }
        });
    }

    /**
     * Who may open the admin panel.
     *
     * Every row in this table can, because this is a single-doctor template and
     * everyone in `users` is staff. The method exists anyway — Filament calls
     * it on every request, and a buyer who later adds a `role` column has
     * exactly one place to put the rule.
     *
     * Accounts are created with `php artisan make:filament-user`, not through
     * any public form. There is no staff registration page, on purpose.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }
}
