<?php

namespace App\Services\SuperAdmin;

use App\Actions\Billing\ActivatePlanGrantAction;
use App\Actions\Billing\RevokePlanGrantAction;
use App\Enums\Billing\PlanGrantSource;
use App\Enums\Billing\PlanGrantStatus;
use App\Models\Billing\Plan;
use App\Models\Billing\PlanGrant;
use App\Models\SuperAdmin\SuperAdmin;
use App\Models\Workspaces\Workspace;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * The billing changes a super admin can make to a workspace. They go
 * through the same pools and actions the product uses — top-up credits are
 * the pool credit packs and referral rewards land in, and plan time is a
 * `manual` `PlanGrant` activated and revoked by the billing actions, so the
 * usage period is re-sized exactly as it would be for a purchase.
 *
 * Every change is audit-logged with the reason the super admin gave.
 */
class SuperAdminWorkspaceBilling
{
    public function __construct(
        private readonly SuperAdminAuditLogger $audit,
        private readonly ActivatePlanGrantAction $activateGrant,
        private readonly RevokePlanGrantAction $revokeGrant,
    ) {}

    /**
     * @return int The workspace's top-up balance after the grant.
     */
    public function grantCredits(Workspace $workspace, int $credits, string $reason, SuperAdmin $actor): int
    {
        return DB::transaction(function () use ($workspace, $credits, $reason, $actor): int {
            $locked = Workspace::query()->whereKey($workspace->getKey())->lockForUpdate()->firstOrFail();
            $before = (int) $locked->topup_credits;

            $locked->increment('topup_credits', $credits);

            $this->audit->record($actor, 'workspace.credits.granted', $locked, ['topup_credits' => $before], [
                'topup_credits' => $before + $credits,
                'credits' => $credits,
                'reason' => $reason,
            ]);

            return $before + $credits;
        });
    }

    public function grantPlanTime(Workspace $workspace, Plan $plan, int $days, string $reason, SuperAdmin $actor): PlanGrant
    {
        return DB::transaction(function () use ($workspace, $plan, $days, $reason, $actor): PlanGrant {
            Workspace::query()->whereKey($workspace->getKey())->lockForUpdate()->firstOrFail();

            $grant = PlanGrant::query()->create([
                'workspace_id' => $workspace->id,
                'plan_id' => $plan->id,
                'source' => PlanGrantSource::Manual,
                'status' => PlanGrantStatus::Pending,
                'price_cents' => 0,
                'expires_at' => now()->addDays($days),
            ]);

            $this->activateGrant->execute($grant);

            $this->audit->record($actor, 'workspace.plan_time.granted', $workspace, null, [
                'plan_grant_id' => $grant->id,
                'plan' => $plan->name,
                'days' => $days,
                'expires_at' => $grant->expires_at->toIso8601String(),
                'reason' => $reason,
            ]);

            return $grant;
        });
    }

    /**
     * Only manual grants can be revoked here. Purchases and referral rewards
     * are undone through refunds and the referral tools, which also settle
     * the money side.
     */
    public function revokePlanGrant(PlanGrant $grant, string $reason, SuperAdmin $actor): void
    {
        if ($grant->source !== PlanGrantSource::Manual) {
            throw new HttpException(422, 'Only manual plan grants can be revoked from the super admin panel.');
        }

        if ($grant->status !== PlanGrantStatus::Active) {
            throw new HttpException(422, 'This plan grant is not active.');
        }

        DB::transaction(function () use ($grant, $reason, $actor): void {
            $this->revokeGrant->execute($grant);

            $this->audit->record($actor, 'workspace.plan_time.revoked', $grant->workspace, ['plan_grant_id' => $grant->id, 'status' => PlanGrantStatus::Active->value], [
                'plan_grant_id' => $grant->id,
                'status' => PlanGrantStatus::Revoked->value,
                'reason' => $reason,
            ]);
        });
    }
}
