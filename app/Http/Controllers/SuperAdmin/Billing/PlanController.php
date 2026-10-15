<?php

namespace App\Http\Controllers\SuperAdmin\Billing;

use App\Http\Controllers\Controller;
use App\Services\SuperAdmin\SuperAdminBillingDirectory;
use Illuminate\View\View;

/**
 * The plan catalogue, read-only: plans are seeded and synced with Stripe,
 * so editing prices here would drift from what Stripe charges.
 */
class PlanController extends Controller
{
    public function __invoke(SuperAdminBillingDirectory $billing): View
    {
        return view('super-admin.billing.plans', ['plans' => $billing->plans()]);
    }
}
