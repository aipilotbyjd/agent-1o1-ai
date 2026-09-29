<?php

namespace App\Console\Commands\Referrals;

use App\Actions\Referrals\RevokeReferralRewardAction;
use App\Enums\Referrals\ReferralRewardStatus;
use App\Models\Referrals\ReferralReward;
use App\Services\Admin\AdminAuditLogger;
use Illuminate\Console\Command;

class RevokeReferralRewardCommand extends Command
{
    protected $signature = 'referrals:revoke {reward : The reward id} {--reason= : Why it is being revoked}';

    protected $description = 'Revokes a referral reward, clawing back what it gave where possible.';

    public function handle(RevokeReferralRewardAction $revoke, AdminAuditLogger $audit): int
    {
        $reward = ReferralReward::query()->find($this->argument('reward'));

        if ($reward === null) {
            $this->error('No reward has that id.');

            return self::FAILURE;
        }

        $reason = $this->option('reason') ?? 'Revoked by an admin';
        $previous = $reward->status;

        if (! $revoke->execute($reward, $reason)) {
            $this->info('That reward is already revoked.');

            return self::SUCCESS;
        }

        $audit->record(null, 'referral_reward.revoked', $reward, ['status' => $previous->value], ['status' => ReferralRewardStatus::Revoked->value, 'reason' => $reason]);

        $this->info("Revoked reward {$reward->id}.");

        return self::SUCCESS;
    }
}
