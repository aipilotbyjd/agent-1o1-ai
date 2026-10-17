<?php

namespace App\Http\Controllers\SuperAdmin\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\Auth\TwoFactorCodeRequest;
use App\Models\SuperAdmin\SuperAdmin;
use App\Services\SuperAdmin\SuperAdminAuditLogger;
use App\Services\SuperAdmin\SuperAdminAuthenticator;
use App\Services\SuperAdmin\SuperAdminTwoFactor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * First-sign-in enrollment. Reached only from the pending state, so the
 * account is not signed in until the authenticator app is confirmed.
 */
class TwoFactorSetupController extends Controller
{
    public function __construct(
        private readonly SuperAdminAuthenticator $authenticator,
        private readonly SuperAdminTwoFactor $twoFactor,
        private readonly SuperAdminAuditLogger $audit,
    ) {}

    public function create(Request $request): View|RedirectResponse
    {
        $superAdmin = $this->pendingSuperAdmin($request);

        if ($superAdmin->hasTwoFactorEnabled()) {
            return redirect()->route('super-admin.two-factor.challenge');
        }

        if (! $superAdmin->two_factor_secret) {
            $this->twoFactor->beginEnrollment($superAdmin);
        }

        return view('super-admin.auth.two-factor-setup', [
            'secret' => trim(chunk_split((string) $superAdmin->two_factor_secret, 4, ' ')),
            'otpauthUrl' => $this->twoFactor->otpauthUrl($superAdmin),
            'qrCodeSvg' => $this->twoFactor->qrCodeSvg($superAdmin),
        ]);
    }

    public function store(TwoFactorCodeRequest $request): RedirectResponse
    {
        $superAdmin = $this->pendingSuperAdmin($request);

        if ($superAdmin->hasTwoFactorEnabled()) {
            return redirect()->route('super-admin.two-factor.challenge');
        }

        $recoveryCodes = $this->twoFactor->confirm($superAdmin, $request->string('code')->toString());

        if ($recoveryCodes === null) {
            if (! $this->authenticator->recordFailedChallenge($request, $superAdmin, 'super_admin.two_factor.enrollment_failed')) {
                return redirect()->route('super-admin.login')->withErrors(['email' => 'Too many invalid codes. Sign in again.']);
            }

            throw ValidationException::withMessages(['code' => 'The code is invalid. Check the time on your device and try again.']);
        }

        $this->audit->record($superAdmin, 'super_admin.two_factor.enabled', $superAdmin);

        $this->authenticator->completeLogin($request, $superAdmin, 'enrollment');

        return redirect()->route('super-admin.recovery-codes')->with('recoveryCodes', $recoveryCodes);
    }

    private function pendingSuperAdmin(Request $request): SuperAdmin
    {
        return $request->attributes->get('pending_super_admin');
    }
}
