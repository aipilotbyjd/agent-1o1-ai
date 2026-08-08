<?php

namespace App\Http\Controllers\Api\Internal\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Auth\ConfirmTwoFactorRequest;
use App\Http\Requests\Api\Internal\V1\Auth\DisableTwoFactorRequest;
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

        return response()->json($result);
    }

    public function confirm(ConfirmTwoFactorRequest $request)
    {
        $recoveryCodes = $this->twoFactor->confirm($request->user(), $request->validated('code'));

        return response()->json(['recovery_codes' => $recoveryCodes]);
    }

    public function disable(DisableTwoFactorRequest $request)
    {
        $this->twoFactor->disable($request->user());

        return response()->noContent();
    }

    /**
     * Note: recovery codes are hashed at rest, so this only reflects how many remain,
     * not their plaintext values — those are only ever shown once, at confirm/regenerate time.
     */
    public function recoveryCodes(Request $request)
    {
        $remaining = count($this->twoFactor->recoveryCodes($request->user()));

        return response()->json(['recovery_codes_remaining' => $remaining]);
    }

    public function regenerateRecoveryCodes(Request $request)
    {
        $recoveryCodes = $this->twoFactor->regenerateRecoveryCodes($request->user());

        return response()->json(['recovery_codes' => $recoveryCodes]);
    }
}
