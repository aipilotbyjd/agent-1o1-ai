<?php

namespace App\Models\SuperAdmin;

use Database\Factories\SuperAdmin\SuperAdminFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Validation\Rules\Password;

/**
 * An super admin panel account, signed in through the `super_admin` guard. Separate from
 * `User` on purpose: nothing a customer account holds can authenticate here.
 * Two-factor authentication is mandatory — see `SuperAdminAuthenticator`.
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class SuperAdmin extends Authenticatable
{
    /** @use HasFactory<SuperAdminFactory> */
    use HasFactory, HasUuids, Notifiable;

    /**
     * Sessions are the only way in; there is no "remember me".
     */
    protected $rememberTokenName = '';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
            'two_factor_last_timestep' => 'integer',
            'password_changed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'disabled_at' => 'datetime',
        ];
    }

    /**
     * Stricter than the customer `Password::defaults()`. Breach checking
     * calls out to Have I Been Pwned, so it only runs in production.
     */
    public static function passwordRule(): Password
    {
        return Password::min(12)
            ->letters()
            ->mixedCase()
            ->numbers()
            ->symbols()
            ->when(app()->isProduction(), fn (Password $rule): Password => $rule->uncompromised());
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_confirmed_at !== null;
    }

    public function isDisabled(): bool
    {
        return $this->disabled_at !== null;
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(SuperAdminAuditLog::class);
    }
}
