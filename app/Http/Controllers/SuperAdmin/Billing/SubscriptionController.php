<?php

namespace App\Http\Controllers\SuperAdmin\Billing;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\IndexSubscriptionsRequest;
use App\Services\SuperAdmin\SuperAdminBillingDirectory;
use Illuminate\View\View;

class SubscriptionController extends Controller
{
    public function __construct(private readonly SuperAdminBillingDirectory $billing) {}

    public function index(IndexSubscriptionsRequest $request): View
    {
        return view('super-admin.billing.subscriptions', [
            'subscriptions' => $this->billing->subscriptions($request->validated()),
            'counts' => $this->billing->subscriptionCounts(),
            'plans' => $this->billing->activePlans(),
            'filters' => $request->validated(),
        ]);
    }
}
