<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Enums\Billing\PlanGrantSource;
use App\Enums\Billing\PlanGrantStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\GrantWorkspaceCreditsRequest;
use App\Http\Requests\SuperAdmin\GrantWorkspacePlanTimeRequest;
use App\Http\Requests\SuperAdmin\RevokePlanGrantRequest;
use App\Models\Billing\Plan;
use App\Models\Billing\PlanGrant;
use App\Models\Workspaces\Workspace;
use App\Services\SuperAdmin\SuperAdminWorkspaceBilling;
use Illuminate\Http\RedirectResponse;

class WorkspaceBillingController extends Controller
{
    public function __construct(private readonly SuperAdminWorkspaceBilling $billing) {}

    public function grantCredits(GrantWorkspaceCreditsRequest $request, Workspace $workspace): RedirectResponse
    {
        $credits = $request->integer('credits');

        $balance = $this->billing->grantCredits($workspace, $credits, $request->string('reason')->toString(), $request->user('super_admin'));

        return $this->back($workspace, 'Added '.number_format($credits).' top-up credits. Balance is now '.number_format($balance).'.');
    }

    public function grantPlanTime(GrantWorkspacePlanTimeRequest $request, Workspace $workspace): RedirectResponse
    {
        $plan = Plan::query()->findOrFail($request->string('plan_id')->toString());
        $days = $request->integer('days');

        $grant = $this->billing->grantPlanTime($workspace, $plan, $days, $request->string('reason')->toString(), $request->user('super_admin'));

        return $this->back($workspace, "Granted {$plan->name} for {$days} ".str('day')->plural($days).', until '.$grant->expires_at->toFormattedDateString().'.');
    }

    public function revokePlanGrant(RevokePlanGrantRequest $request, Workspace $workspace, PlanGrant $grant): RedirectResponse
    {
        abort_unless($grant->workspace_id === $workspace->id, 404);

        if ($grant->source !== PlanGrantSource::Manual || $grant->status !== PlanGrantStatus::Active) {
            return $this->back($workspace, null)->withErrors(['reason' => 'Only active manual plan grants can be revoked here.'], 'revokePlanGrant');
        }

        $this->billing->revokePlanGrant($grant, $request->string('reason')->toString(), $request->user('super_admin'));

        return $this->back($workspace, 'Plan grant revoked.');
    }

    private function back(Workspace $workspace, ?string $status): RedirectResponse
    {
        $redirect = redirect()->route('super-admin.workspaces.show', $workspace);

        return $status !== null ? $redirect->with('status', $status) : $redirect;
    }
}
