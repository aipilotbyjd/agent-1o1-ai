<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Auth\TwoFactorCodeRequest;
use App\Models\Admin\Admin;
use App\Services\Admin\AdminAuditLogger;
use App\Services\Admin\AdminAuthenticator;
use App\Services\Admin\AdminTwoFactor;
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
        private readonly AdminAuthenticator $authenticator,
        private readonly AdminTwoFactor $twoFactor,
        private readonly AdminAuditLogger $audit,
    ) {}

    public function create(Request $request): View|RedirectResponse
    {
        $admin = $this->pendingAdmin($request);

        if ($admin->hasTwoFactorEnabled()) {
            return redirect()->route('panel.two-factor.challenge');
        }

        if (! $admin->two_factor_secret) {
            $this->twoFactor->beginEnrollment($admin);
        }

        return view('admin.auth.two-factor-setup', [
            'secret' => trim(chunk_split((string) $admin->two_factor_secret, 4, ' ')),
            'otpauthUrl' => $this->twoFactor->otpauthUrl($admin),
        ]);
    }

    public function store(TwoFactorCodeRequest $request): RedirectResponse
    {
        $admin = $this->pendingAdmin($request);

        if ($admin->hasTwoFactorEnabled()) {
            return redirect()->route('panel.two-factor.challenge');
        }

        $recoveryCodes = $this->twoFactor->confirm($admin, $request->string('code')->toString());

        if ($recoveryCodes === null) {
            if (! $this->authenticator->recordFailedChallenge($request, $admin, 'admin.two_factor.enrollment_failed')) {
                return redirect()->route('panel.login')->withErrors(['email' => 'Too many invalid codes. Sign in again.']);
            }

            throw ValidationException::withMessages(['code' => 'The code is invalid. Check the time on your device and try again.']);
        }

        $this->audit->record($admin, 'admin.two_factor.enabled', $admin);

        $this->authenticator->completeLogin($request, $admin, 'enrollment');

        return redirect()->route('panel.recovery-codes')->with('recoveryCodes', $recoveryCodes);
    }

    private function pendingAdmin(Request $request): Admin
    {
        return $request->attributes->get('pending_admin');
    }
}
