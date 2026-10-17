<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\ConfirmSupportActionRequest;
use App\Models\User;
use App\Services\SuperAdmin\SuperAdminUserSupport;
use Illuminate\Http\RedirectResponse;

class UserSupportController extends Controller
{
    public function __construct(private readonly SuperAdminUserSupport $support) {}

    public function signOutEverywhere(ConfirmSupportActionRequest $request, User $user): RedirectResponse
    {
        $count = $this->support->signOutEverywhere($user, $request->user('super_admin'));

        return $this->back($user, "Signed out of {$count} API ".str('session')->plural($count).'.');
    }

    public function resetTwoFactor(ConfirmSupportActionRequest $request, User $user): RedirectResponse
    {
        if (! $user->hasTwoFactorEnabled()) {
            return $this->back($user, 'Two-factor is not enabled for this user.');
        }

        $this->support->resetTwoFactor($user, $request->user('super_admin'));

        return $this->back($user, 'Two-factor reset. The user can set it up again.');
    }

    public function resendVerification(ConfirmSupportActionRequest $request, User $user): RedirectResponse
    {
        if ($user->hasVerifiedEmail()) {
            return $this->back($user, 'This email is already verified.');
        }

        $this->support->resendVerificationEmail($user, $request->user('super_admin'));

        return $this->back($user, 'Verification email sent.');
    }

    private function back(User $user, string $status): RedirectResponse
    {
        return redirect()->route('super-admin.users.show', $user)->with('status', $status);
    }
}
