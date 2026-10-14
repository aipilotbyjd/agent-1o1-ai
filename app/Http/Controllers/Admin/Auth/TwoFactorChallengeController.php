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

class TwoFactorChallengeController extends Controller
{
    public function __construct(
        private readonly AdminAuthenticator $authenticator,
        private readonly AdminTwoFactor $twoFactor,
        private readonly AdminAuditLogger $audit,
    ) {}

    public function create(Request $request): View|RedirectResponse
    {
        if (! $this->pendingAdmin($request)->hasTwoFactorEnabled()) {
            return redirect()->route('panel.two-factor.setup');
        }

        return view('admin.auth.two-factor-challenge');
    }

    public function store(TwoFactorCodeRequest $request): RedirectResponse
    {
        $admin = $this->pendingAdmin($request);

        if (! $admin->hasTwoFactorEnabled()) {
            return redirect()->route('panel.two-factor.setup');
        }

        $method = $this->twoFactor->verify($admin, $request->string('code')->toString());

        if ($method === null) {
            if (! $this->authenticator->recordFailedChallenge($request, $admin, 'admin.two_factor.failed')) {
                return redirect()->route('panel.login')->withErrors(['email' => 'Too many invalid codes. Sign in again.']);
            }

            throw ValidationException::withMessages(['code' => 'The code is invalid or has already been used.']);
        }

        if ($method === 'recovery') {
            $this->audit->record($admin, 'admin.recovery_code.used', $admin, null, [
                'remaining' => $this->twoFactor->remainingRecoveryCodes($admin),
            ]);
        }

        $this->authenticator->completeLogin($request, $admin, $method);

        return redirect()->intended(route('panel.dashboard'));
    }

    private function pendingAdmin(Request $request): Admin
    {
        return $request->attributes->get('pending_admin');
    }
}
