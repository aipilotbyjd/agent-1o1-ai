<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\RegenerateRecoveryCodesRequest;
use App\Http\Requests\SuperAdmin\UpdateSuperAdminPasswordRequest;
use App\Models\SuperAdmin\SuperAdmin;
use App\Services\SuperAdmin\SuperAdminAuditLogger;
use App\Services\SuperAdmin\SuperAdminAuthenticator;
use App\Services\SuperAdmin\SuperAdminTwoFactor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function __construct(
        private readonly SuperAdminAuthenticator $authenticator,
        private readonly SuperAdminTwoFactor $twoFactor,
        private readonly SuperAdminAuditLogger $audit,
    ) {}

    public function show(Request $request): View
    {
        $superAdmin = $this->superAdmin($request);

        return view('super-admin.account', [
            'super_admin' => $superAdmin,
            'remainingRecoveryCodes' => $this->twoFactor->remainingRecoveryCodes($superAdmin),
        ]);
    }

    /**
     * Changing the password keeps this session and ends every other one
     * (see `EnsureSuperAdminSessionIsValid`).
     */
    public function updatePassword(UpdateSuperAdminPasswordRequest $request): RedirectResponse
    {
        $superAdmin = $this->superAdmin($request);

        $superAdmin->forceFill([
            'password' => $request->string('password')->toString(),
            'password_changed_at' => now(),
        ])->save();

        $request->session()->regenerate();
        $this->authenticator->refreshPasswordFingerprint($request, $superAdmin);

        $this->audit->record($superAdmin, 'super_admin.password.changed', $superAdmin);

        return redirect()->route('super-admin.account')->with('status', 'Password updated. Other sessions have been signed out.');
    }

    public function regenerateRecoveryCodes(RegenerateRecoveryCodesRequest $request): RedirectResponse
    {
        $superAdmin = $this->superAdmin($request);

        $recoveryCodes = $this->twoFactor->regenerateRecoveryCodes($superAdmin);

        $this->audit->record($superAdmin, 'super_admin.recovery_codes.regenerated', $superAdmin);

        return redirect()->route('super-admin.recovery-codes')->with('recoveryCodes', $recoveryCodes);
    }

    /**
     * Shown once, straight after codes are generated; a refresh or a later
     * visit has nothing to show.
     */
    public function showRecoveryCodes(Request $request): View|RedirectResponse
    {
        $recoveryCodes = $request->session()->get('recoveryCodes');

        if (! is_array($recoveryCodes)) {
            return redirect()->route('super-admin.account');
        }

        return view('super-admin.recovery-codes', ['recoveryCodes' => $recoveryCodes]);
    }

    private function superAdmin(Request $request): SuperAdmin
    {
        return $request->user('super_admin');
    }
}
