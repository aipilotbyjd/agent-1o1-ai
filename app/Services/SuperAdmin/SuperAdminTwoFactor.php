<?php

namespace App\Services\SuperAdmin;

use App\Models\SuperAdmin\SuperAdmin;
use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * TOTP two-factor authentication for super admin panel accounts.
 *
 * Every accepted code records its time step, and a code is accepted only for
 * a later step than the last one used — so a code seen over a shoulder or
 * captured in transit can't be replayed inside its 30-second window.
 * Recovery codes are stored hashed and each works once.
 */
class SuperAdminTwoFactor
{
    private const int RECOVERY_CODE_COUNT = 8;

    public function __construct(private readonly Google2FA $google2fa) {}

    /**
     * Starts (or restarts) enrollment with a fresh secret. The account keeps
     * no confirmed second factor until `confirm()` succeeds.
     */
    public function beginEnrollment(SuperAdmin $superAdmin): string
    {
        $secret = $this->google2fa->generateSecretKey(32);

        $superAdmin->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_timestep' => null,
        ])->save();

        return $secret;
    }

    public function otpauthUrl(SuperAdmin $superAdmin): string
    {
        return $this->google2fa->getQRCodeUrl(config('app.name').' SuperAdmin', $superAdmin->email, (string) $superAdmin->two_factor_secret);
    }

    /**
     * Rendered on the server as inline SVG, so the secret never goes to a
     * third-party QR service.
     */
    public function qrCodeSvg(SuperAdmin $superAdmin): string
    {
        $svg = (new Writer(new ImageRenderer(
            new RendererStyle(192, 0, null, null, Fill::uniformColor(new Rgb(255, 255, 255), new Rgb(24, 24, 27))),
            new SvgImageBackEnd,
        )))->writeString($this->otpauthUrl($superAdmin));

        return trim(substr($svg, strpos($svg, "\n") + 1));
    }

    /**
     * @return array<int, string>|null The plain recovery codes, or null if the code was wrong.
     */
    public function confirm(SuperAdmin $superAdmin, string $code): ?array
    {
        if (! $superAdmin->two_factor_secret || ! $this->verifyTotp($superAdmin, $code)) {
            return null;
        }

        $superAdmin->forceFill(['two_factor_confirmed_at' => now()])->save();

        return $this->regenerateRecoveryCodes($superAdmin);
    }

    /**
     * @return 'totp'|'recovery'|null How the code was accepted, or null if it wasn't.
     */
    public function verify(SuperAdmin $superAdmin, string $code): ?string
    {
        if (! $superAdmin->hasTwoFactorEnabled()) {
            return null;
        }

        if ($this->verifyTotp($superAdmin, $code)) {
            return 'totp';
        }

        return $this->consumeRecoveryCode($superAdmin, $code) ? 'recovery' : null;
    }

    /**
     * @return array<int, string>
     */
    public function regenerateRecoveryCodes(SuperAdmin $superAdmin): array
    {
        $plainCodes = collect(range(1, self::RECOVERY_CODE_COUNT))
            ->map(fn (): string => Str::lower(Str::random(10).'-'.Str::random(10)))
            ->all();

        $superAdmin->forceFill([
            'two_factor_recovery_codes' => array_map(fn (string $code): string => Hash::make($code), $plainCodes),
        ])->save();

        return $plainCodes;
    }

    public function remainingRecoveryCodes(SuperAdmin $superAdmin): int
    {
        return count($superAdmin->two_factor_recovery_codes ?? []);
    }

    public function reset(SuperAdmin $superAdmin): void
    {
        $superAdmin->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_timestep' => null,
        ])->save();
    }

    private function verifyTotp(SuperAdmin $superAdmin, string $code): bool
    {
        $code = preg_replace('/\s+/', '', $code);

        if (! preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        return DB::transaction(function () use ($superAdmin, $code): bool {
            $lastTimestep = SuperAdmin::query()->whereKey($superAdmin->getKey())->lockForUpdate()->value('two_factor_last_timestep');

            $timestep = $this->google2fa->verifyKeyNewer((string) $superAdmin->two_factor_secret, $code, (int) $lastTimestep);

            if ($timestep === false) {
                return false;
            }

            $superAdmin->forceFill(['two_factor_last_timestep' => $timestep])->save();

            return true;
        });
    }

    private function consumeRecoveryCode(SuperAdmin $superAdmin, string $code): bool
    {
        $code = Str::lower(trim($code));

        return DB::transaction(function () use ($superAdmin, $code): bool {
            $recoveryCodes = SuperAdmin::query()->whereKey($superAdmin->getKey())->lockForUpdate()->first()?->two_factor_recovery_codes ?? [];

            foreach ($recoveryCodes as $index => $hashedCode) {
                if (Hash::check($code, $hashedCode)) {
                    unset($recoveryCodes[$index]);
                    $superAdmin->forceFill(['two_factor_recovery_codes' => array_values($recoveryCodes)])->save();

                    return true;
                }
            }

            return false;
        });
    }
}
