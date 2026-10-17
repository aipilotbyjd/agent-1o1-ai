<?php

namespace App\Http\Controllers\SuperAdmin\Billing;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\IndexPlanGrantsRequest;
use App\Services\SuperAdmin\SuperAdminBillingDirectory;
use Illuminate\View\View;

class PlanGrantController extends Controller
{
    public function __construct(private readonly SuperAdminBillingDirectory $billing) {}

    public function index(IndexPlanGrantsRequest $request): View
    {
        return view('super-admin.billing.plan-grants', [
            'grants' => $this->billing->planGrants($request->validated()),
            'filters' => $request->validated(),
        ]);
    }
}
