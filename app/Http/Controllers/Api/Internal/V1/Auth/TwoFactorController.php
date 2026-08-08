<?php

namespace App\Http\Controllers\Api\Internal\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Auth\ConfirmTwoFactorRequest;
use App\Http\Requests\Api\Internal\V1\Auth\DisableTwoFactorRequest;
use App\Http\Responses\ApiResponse;
use App\Services\Auth\TwoFactorAuthService;
use Illuminate\Http\Request;

class TwoFactorController extends Controller
{
    public function __construct(
        private readonly TwoFactorAuthService $twoFactor,
    ) {}

    public function enable(Request $request)
    {
        $result = $this->twoFactor->enable($request->user());

        return ApiResponse::success(
            $result,
            'Scan the QR code with your authenticator app, then confirm with a code to finish enabling two-factor authentication.',
        );
    }

    public function confirm(ConfirmTwoFactorRequest $request)
    {
        $recoveryCodes = $this->twoFactor->confirm($request->user(), $request->validated('code'));

        return ApiResponse::success(
            ['recovery_codes' => $recoveryCodes],
            'Two-factor authentication enabled. Store these recovery codes somewhere safe — they will not be shown again.',
        );
    }

    public function disable(DisableTwoFactorRequest $request)
    {
        $this->twoFactor->disable($request->user());

        return ApiResponse::noContent();
    }

    /**
     * Note: recovery codes are hashed at rest, so this only reflects how many remain,
     * not their plaintext values — those are only ever shown once, at confirm/regenerate time.
     */
    public function recoveryCodes(Request $request)
    {
        $remaining = count($this->twoFactor->recoveryCodes($request->user()));

        return ApiResponse::success(['recovery_codes_remaining' => $remaining]);
    }

    public function regenerateRecoveryCodes(Request $request)
    {
        $recoveryCodes = $this->twoFactor->regenerateRecoveryCodes($request->user());

        return ApiResponse::success(
            ['recovery_codes' => $recoveryCodes],
            'Recovery codes regenerated. Store these somewhere safe — they will not be shown again.',
        );
    }
}
