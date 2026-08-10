<?php

namespace App\Http\Controllers\Api\Internal\V1\Billing;

use App\Actions\Billing\CancelSubscriptionAction;
use App\Actions\Billing\CheckoutSubscriptionAction;
use App\Actions\Billing\ResumeSubscriptionAction;
use App\Enums\Billing\BillingInterval;
use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Billing\CheckoutSubscriptionRequest;
use App\Http\Resources\Api\Internal\V1\Billing\SubscriptionResource;
use App\Http\Responses\ApiResponse;
use App\Models\Billing\Plan;
use App\Models\Workspaces\Workspace;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function show(Request $request, Workspace $workspace)
    {
        $this->requirePermission(Permission::BillingView);

        $subscription = $workspace->subscription('default')?->load('plan');

        return ApiResponse::success(['subscription' => $subscription ? SubscriptionResource::make($subscription) : null]);
    }

    public function checkout(CheckoutSubscriptionRequest $request, Workspace $workspace, CheckoutSubscriptionAction $checkout)
    {
        $this->requirePermission(Permission::BillingManage);

        $plan = Plan::findOrFail($request->validated('plan_id'));
        $interval = BillingInterval::from($request->validated('interval'));

        $checkoutUrl = $checkout->execute($workspace, $plan, $interval);

        if ($checkoutUrl === null) {
            return ApiResponse::success(['subscription' => SubscriptionResource::make($workspace->subscription('default')?->load('plan'))], 'Subscription updated.');
        }

        return ApiResponse::success(['checkout_url' => $checkoutUrl], 'Checkout session created.');
    }

    public function cancel(Request $request, Workspace $workspace, CancelSubscriptionAction $cancel)
    {
        $this->requirePermission(Permission::BillingManage);

        $subscription = $cancel->execute($workspace);

        return ApiResponse::success(['subscription' => SubscriptionResource::make($subscription->load('plan'))], 'Subscription canceled.');
    }

    public function resume(Request $request, Workspace $workspace, ResumeSubscriptionAction $resume)
    {
        $this->requirePermission(Permission::BillingManage);

        $subscription = $resume->execute($workspace);

        return ApiResponse::success(['subscription' => SubscriptionResource::make($subscription->load('plan'))], 'Subscription resumed.');
    }
}
