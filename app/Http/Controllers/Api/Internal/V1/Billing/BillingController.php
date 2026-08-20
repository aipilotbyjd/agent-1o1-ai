<?php

namespace App\Http\Controllers\Api\Internal\V1\Billing;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Internal\V1\Billing\PlanGrantResource;
use App\Http\Resources\Api\Internal\V1\Billing\PlanResource;
use App\Http\Resources\Api\Internal\V1\Billing\SubscriptionResource;
use App\Http\Resources\Api\Internal\V1\Billing\UsagePeriodResource;
use App\Http\Responses\ApiResponse;
use App\Models\Workspaces\Workspace;

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
     */
    public function overview(Workspace $workspace)
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
        ]);
    }
}
