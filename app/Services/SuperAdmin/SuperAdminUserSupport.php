<?php

namespace App\Services\SuperAdmin;

use App\Models\SuperAdmin\SuperAdmin;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\RefreshToken;

/**
 * The few support actions a super admin can take on a customer account.
 * Each one is audit-logged; none of them touches the customer API code.
 */
class SuperAdminUserSupport
{
    public function __construct(private readonly SuperAdminAuditLogger $audit) {}

    /**
     * Revokes every API access and refresh token. Returns how many access tokens ended.
     */
    public function signOutEverywhere(User $user, SuperAdmin $actor): int
    {
        return DB::transaction(function () use ($user, $actor): int {
            $tokenIds = $user->tokens()->where('revoked', false)->pluck('id');

            $user->tokens()->whereIn('id', $tokenIds)->update(['revoked' => true]);
            RefreshToken::query()->whereIn('access_token_id', $tokenIds)->update(['revoked' => true]);

            $this->audit->record($actor, 'user.signed_out_everywhere', $user, null, ['tokens_revoked' => $tokenIds->count()]);

            return $tokenIds->count();
        });
    }

    /**
     * For a customer locked out of their authenticator and recovery codes.
     */
    public function resetTwoFactor(User $user, SuperAdmin $actor): void
    {
        DB::transaction(function () use ($user, $actor): void {
            $user->forceFill([
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
            ])->save();

            $this->audit->record($actor, 'user.two_factor.reset', $user, ['two_factor' => 'enabled'], ['two_factor' => 'off']);
        });
    }

    public function resendVerificationEmail(User $user, SuperAdmin $actor): void
    {
        $user->sendEmailVerificationNotification();

        $this->audit->record($actor, 'user.verification_email.resent', $user);
    }
}
