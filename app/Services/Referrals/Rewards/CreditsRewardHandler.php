<?php

namespace App\Services\Referrals\Rewards;

use App\Models\Referrals\ReferralReward;
use App\Models\Workspaces\Workspace;

/**
 * Adds to the workspace's non-expiring `topup_credits` pool — the same pool
 * credit packs land in, spent after the plan allowance.
 */
class CreditsRewardHandler implements RewardHandler
{
    public function grant(ReferralReward $reward): void
    {
        if ($reward->workspace_id === null || ($reward->credits ?? 0) <= 0) {
            return;
        }

        $workspace = Workspace::query()->whereKey($reward->workspace_id)->lockForUpdate()->first();

        $workspace?->increment('topup_credits', $reward->credits);
    }

    /**
     * Takes back what is still there. `topup_credits` is unsigned and may
     * already be spent, so the clawback stops at zero and records how much
     * it actually recovered.
     */
    public function revoke(ReferralReward $reward): void
    {
        if ($reward->workspace_id === null || ($reward->credits ?? 0) <= 0) {
            return;
        }

        $workspace = Workspace::query()->whereKey($reward->workspace_id)->lockForUpdate()->first();

        if ($workspace === null) {
            return;
        }

        $recovered = min($reward->credits, (int) $workspace->topup_credits);

        if ($recovered > 0) {
            $workspace->decrement('topup_credits', $recovered);
        }

        $reward->credits_clawed_back = $recovered;
    }
}
