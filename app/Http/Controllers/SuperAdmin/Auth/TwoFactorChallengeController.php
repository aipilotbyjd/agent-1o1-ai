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

class TwoFactorChallengeController extends Controller
{
    public function __construct(
        private readonly SuperAdminAuthenticator $authenticator,
        private readonly SuperAdminTwoFactor $twoFactor,
        private readonly SuperAdminAuditLogger $audit,
    ) {}

    public function create(Request $request): View|RedirectResponse
    {
        if (! $this->pendingSuperAdmin($request)->hasTwoFactorEnabled()) {
            return redirect()->route('super-admin.two-factor.setup');
        }

        return view('super-admin.auth.two-factor-challenge');
    }

    public function store(TwoFactorCodeRequest $request): RedirectResponse
    {
        $superAdmin = $this->pendingSuperAdmin($request);

        if (! $superAdmin->hasTwoFactorEnabled()) {
            return redirect()->route('super-admin.two-factor.setup');
        }

        $method = $this->twoFactor->verify($superAdmin, $request->string('code')->toString());

        if ($method === null) {
            if (! $this->authenticator->recordFailedChallenge($request, $superAdmin, 'super_admin.two_factor.failed')) {
                return redirect()->route('super-admin.login')->withErrors(['email' => 'Too many invalid codes. Sign in again.']);
            }

            throw ValidationException::withMessages(['code' => 'The code is invalid or has already been used.']);
        }

        if ($method === 'recovery') {
            $this->audit->record($superAdmin, 'super_admin.recovery_code.used', $superAdmin, null, [
                'remaining' => $this->twoFactor->remainingRecoveryCodes($superAdmin),
            ]);
        }

        $this->authenticator->completeLogin($request, $superAdmin, $method);

        return redirect()->intended(route('super-admin.dashboard'));
    }

    private function pendingSuperAdmin(Request $request): SuperAdmin
    {
        return $request->attributes->get('pending_super_admin');
    }
}
