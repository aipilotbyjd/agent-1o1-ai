<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RegenerateRecoveryCodesRequest;
use App\Http\Requests\Admin\UpdateAdminPasswordRequest;
use App\Models\Admin\Admin;
use App\Services\Admin\AdminAuditLogger;
use App\Services\Admin\AdminAuthenticator;
use App\Services\Admin\AdminTwoFactor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function __construct(
        private readonly AdminAuthenticator $authenticator,
        private readonly AdminTwoFactor $twoFactor,
        private readonly AdminAuditLogger $audit,
    ) {}

    public function show(Request $request): View
    {
        $admin = $this->admin($request);

        return view('admin.account', [
            'admin' => $admin,
            'remainingRecoveryCodes' => $this->twoFactor->remainingRecoveryCodes($admin),
        ]);
    }

    /**
     * Changing the password keeps this session and ends every other one
     * (see `EnsureAdminSessionIsValid`).
     */
    public function updatePassword(UpdateAdminPasswordRequest $request): RedirectResponse
    {
        $admin = $this->admin($request);

        $admin->forceFill([
            'password' => $request->string('password')->toString(),
            'password_changed_at' => now(),
        ])->save();

        $request->session()->regenerate();
        $this->authenticator->refreshPasswordFingerprint($request, $admin);

        $this->audit->record($admin, 'admin.password.changed', $admin);

        return redirect()->route('panel.account')->with('status', 'Password updated. Other sessions have been signed out.');
    }

    public function regenerateRecoveryCodes(RegenerateRecoveryCodesRequest $request): RedirectResponse
    {
        $admin = $this->admin($request);

        $recoveryCodes = $this->twoFactor->regenerateRecoveryCodes($admin);

        $this->audit->record($admin, 'admin.recovery_codes.regenerated', $admin);

        return redirect()->route('panel.recovery-codes')->with('recoveryCodes', $recoveryCodes);
    }

    /**
     * Shown once, straight after codes are generated; a refresh or a later
     * visit has nothing to show.
     */
    public function showRecoveryCodes(Request $request): View|RedirectResponse
    {
        $recoveryCodes = $request->session()->get('recoveryCodes');

        if (! is_array($recoveryCodes)) {
            return redirect()->route('panel.account');
        }

        return view('admin.recovery-codes', ['recoveryCodes' => $recoveryCodes]);
    }

    private function admin(Request $request): Admin
    {
        return $request->user('admin');
    }
}
