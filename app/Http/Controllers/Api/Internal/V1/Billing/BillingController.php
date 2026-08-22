<?php

namespace App\Http\Controllers\Api\Internal\V1\Billing;

use App\Enums\Billing\PlanLimit;
use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Internal\V1\Billing\PlanGrantResource;
use App\Http\Resources\Api\Internal\V1\Billing\PlanResource;
use App\Http\Resources\Api\Internal\V1\Billing\SubscriptionResource;
use App\Http\Resources\Api\Internal\V1\Billing\UsagePeriodResource;
use App\Http\Responses\ApiResponse;
use App\Models\Billing\Plan;
use App\Models\Workspaces\Workspace;
use App\Services\Billing\PlanLimitGate;

class BillingController extends Controller
{
    /**
     * A single aggregate read for the billing settings screen: the active
     * subscription (with its plan), any non-subscription entitlement (a
     * lifetime purchase or comp), and the current usage period's credit
     * balance. Transaction history and credit-pack purchases live on their
     * own endpoints since they paginate independently.
     *
     * `current_plan` is the one that actually applies — with both a
     * subscription and a grant in play, neither alone answers "what plan am
     * I on", so the screen should render this rather than `subscription.plan`.
     *
     * `credits_available` is the number that actually gates a run: the
     * period's remaining plan allowance plus the non-expiring `topup_credits`
     * bought via credit packs. `null` means unlimited.
     *
     * `limits` reports what `PlanLimitGate` will actually enforce on the next
     * create — render the "2 of 3 workflows" counter from this rather than
     * from `current_plan.limits`, which carries the raw cap with no usage
     * beside it. A `max` of `null` means unlimited.
     *
     * `dunning` is non-null only while Stripe is failing to collect. Because
     * this app grants no grace period, `subscription` will already be null at
     * that point — the workspace has been dropped to the free plan — so this
     * is the only thing on the response that can explain why. Render it as a
     * banner pointing at `POST /billing/portal`.
     */
    public function overview(Workspace $workspace, PlanLimitGate $limits)
    {
        $this->requirePermission(Permission::BillingView);

        $subscription = $workspace->activeSubscription()?->load('plan');
        $planGrant = $workspace->activePlanGrant();
        $currentPlan = $workspace->currentPlan();

        return ApiResponse::success([
            'subscription' => $subscription ? SubscriptionResource::make($subscription) : null,
            'plan_grant' => $planGrant ? PlanGrantResource::make($planGrant->load('plan')) : null,
            'current_plan' => $currentPlan ? PlanResource::make($currentPlan) : null,
            'usage_period' => UsagePeriodResource::make($workspace->currentUsagePeriod()),
            'topup_credits' => $workspace->topup_credits,
            'credits_available' => $workspace->availableCredits(),
            'limits' => $this->limitUsage($workspace, $currentPlan, $limits),
            'dunning' => $this->dunning($workspace),
        ]);
    }

    /**
     * Read off the raw subscription row rather than `activeSubscription()`,
     * which is null precisely when this matters.
     *
     * @return array{started_at: string, attempts: int, invoice_id: string|null}|null
     */
    private function dunning(Workspace $workspace): ?array
    {
        $subscription = $workspace->subscription('default');

        if (! $subscription?->inDunning()) {
            return null;
        }

        return [
            'started_at' => $subscription->dunning_started_at->toIso8601String(),
            'attempts' => $subscription->dunning_attempts,
            'invoice_id' => $subscription->dunning_invoice_id,
        ];
    }

    /**
     * @return array<string, array{used: int, max: int|null}>
     */
    private function limitUsage(Workspace $workspace, ?Plan $plan, PlanLimitGate $limits): array
    {
        $usage = [];

        foreach (PlanLimit::cases() as $limit) {
            $usage[$limit->value] = [
                'used' => $limits->usage($workspace, $limit),
                'max' => $plan?->limit($limit),
            ];
        }

        return $usage;
    }
}
