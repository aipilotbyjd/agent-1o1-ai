<?php

namespace App\Http\Controllers\Api\Internal\V1\Billing;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Internal\V1\Billing\SubscriptionResource;
use App\Http\Resources\Api\Internal\V1\Billing\UsagePeriodResource;
use App\Http\Responses\ApiResponse;
use App\Models\Workspaces\Workspace;

class BillingController extends Controller
{
    /**
     * A single aggregate read for the billing settings screen: the active
     * subscription (with its plan) and the current usage period's credit
     * balance. Transaction history and credit-pack purchases live on their
     * own endpoints since they paginate independently.
     */
    public function overview(Workspace $workspace)
    {
        $this->requirePermission(Permission::BillingView);

        $subscription = $workspace->subscription('default')?->load('plan');

        return ApiResponse::success([
            'subscription' => $subscription ? SubscriptionResource::make($subscription) : null,
            'usage_period' => UsagePeriodResource::make($workspace->currentUsagePeriod()),
        ]);
    }
}
