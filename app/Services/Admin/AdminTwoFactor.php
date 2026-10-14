<?php

namespace App\Services\Admin;

use App\Models\Admin\Admin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * TOTP two-factor authentication for admin panel accounts.
 *
 * Every accepted code records its time step, and a code is accepted only for
 * a later step than the last one used — so a code seen over a shoulder or
 * captured in transit can't be replayed inside its 30-second window.
 * Recovery codes are stored hashed and each works once.
 */
class AdminTwoFactor
{
    private const int RECOVERY_CODE_COUNT = 8;

    public function __construct(private readonly Google2FA $google2fa) {}

    /**
     * Starts (or restarts) enrollment with a fresh secret. The account keeps
     * no confirmed second factor until `confirm()` succeeds.
     */
    public function beginEnrollment(Admin $admin): string
    {
        $secret = $this->google2fa->generateSecretKey(32);

        $admin->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_timestep' => null,
        ])->save();

        return $secret;
    }

    public function otpauthUrl(Admin $admin): string
    {
        return $this->google2fa->getQRCodeUrl(config('app.name').' Admin', $admin->email, (string) $admin->two_factor_secret);
    }

    /**
     * @return array<int, string>|null The plain recovery codes, or null if the code was wrong.
     */
    public function confirm(Admin $admin, string $code): ?array
    {
        if (! $admin->two_factor_secret || ! $this->verifyTotp($admin, $code)) {
            return null;
        }

        $admin->forceFill(['two_factor_confirmed_at' => now()])->save();

        return $this->regenerateRecoveryCodes($admin);
    }

    /**
     * @return 'totp'|'recovery'|null How the code was accepted, or null if it wasn't.
     */
    public function verify(Admin $admin, string $code): ?string
    {
        if (! $admin->hasTwoFactorEnabled()) {
            return null;
        }

        if ($this->verifyTotp($admin, $code)) {
            return 'totp';
        }

        return $this->consumeRecoveryCode($admin, $code) ? 'recovery' : null;
    }

    /**
     * @return array<int, string>
     */
    public function regenerateRecoveryCodes(Admin $admin): array
    {
        $plainCodes = collect(range(1, self::RECOVERY_CODE_COUNT))
            ->map(fn (): string => Str::lower(Str::random(10).'-'.Str::random(10)))
            ->all();

        $admin->forceFill([
            'two_factor_recovery_codes' => array_map(fn (string $code): string => Hash::make($code), $plainCodes),
        ])->save();

        return $plainCodes;
    }

    public function remainingRecoveryCodes(Admin $admin): int
    {
        return count($admin->two_factor_recovery_codes ?? []);
    }

    public function reset(Admin $admin): void
    {
        $admin->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_timestep' => null,
        ])->save();
    }

    private function verifyTotp(Admin $admin, string $code): bool
    {
        $code = preg_replace('/\s+/', '', $code);

        if (! preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        return DB::transaction(function () use ($admin, $code): bool {
            $lastTimestep = Admin::query()->whereKey($admin->getKey())->lockForUpdate()->value('two_factor_last_timestep');

            $timestep = $this->google2fa->verifyKeyNewer((string) $admin->two_factor_secret, $code, (int) $lastTimestep);

            if ($timestep === false) {
                return false;
            }

            $admin->forceFill(['two_factor_last_timestep' => $timestep])->save();

            return true;
        });
    }

    private function consumeRecoveryCode(Admin $admin, string $code): bool
    {
        $code = Str::lower(trim($code));

        return DB::transaction(function () use ($admin, $code): bool {
            $recoveryCodes = Admin::query()->whereKey($admin->getKey())->lockForUpdate()->first()?->two_factor_recovery_codes ?? [];

            foreach ($recoveryCodes as $index => $hashedCode) {
                if (Hash::check($code, $hashedCode)) {
                    unset($recoveryCodes[$index]);
                    $admin->forceFill(['two_factor_recovery_codes' => array_values($recoveryCodes)])->save();

                    return true;
                }
            }

            return false;
        });
    }
}
