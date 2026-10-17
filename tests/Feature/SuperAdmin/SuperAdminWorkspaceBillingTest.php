<?php

use App\Enums\Billing\PlanGrantSource;
use App\Enums\Billing\PlanGrantStatus;
use App\Models\Billing\Plan;
use App\Models\Billing\PlanGrant;
use App\Models\SuperAdmin\SuperAdminAuditLog;
use App\Models\Workspaces\Workspace;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->withoutVite();
    Notification::fake();
});

function billedWorkspace(): Workspace
{
    return referralUser()->ownedWorkspaces()->first();
}

it('adds top-up credits with a reason and logs it', function () {
    $workspace = billedWorkspace();
    $workspace->forceFill(['topup_credits' => 100])->save();
    $admin = superAdmin();
    signInAsSuperAdmin($admin);

    $this->post(route('super-admin.workspaces.credits.grant', $workspace), [
        'credits' => 2500,
        'reason' => 'Refund for failed runs',
        'current_password' => 'password',
    ])->assertRedirect(route('super-admin.workspaces.show', $workspace))->assertSessionHas('status');

    expect($workspace->fresh()->topup_credits)->toBe(2600);

    $entry = SuperAdminAuditLog::query()->where('action', 'workspace.credits.granted')->sole();

    expect($entry->super_admin_id)->toBe($admin->id)
        ->and($entry->subject_id)->toBe($workspace->id)
        ->and($entry->before)->toBe(['topup_credits' => 100])
        ->and($entry->after)->toMatchArray(['topup_credits' => 2600, 'credits' => 2500, 'reason' => 'Refund for failed runs']);
});

it('refuses a credit grant without the right password, a reason or a sane amount', function (array $input, string $field) {
    $workspace = billedWorkspace();
    signInAsSuperAdmin(superAdmin());

    $this->post(route('super-admin.workspaces.credits.grant', $workspace), [
        'credits' => 100,
        'reason' => 'Goodwill credit',
        'current_password' => 'password',
        ...$input,
    ])->assertSessionHasErrorsIn('grantCredits', $field);

    expect($workspace->fresh()->topup_credits)->toBe(0)
        ->and(SuperAdminAuditLog::query()->where('action', 'workspace.credits.granted')->exists())->toBeFalse();
})->with([
    'wrong password' => [['current_password' => 'wrong'], 'current_password'],
    'no reason' => [['reason' => ''], 'reason'],
    'zero credits' => [['credits' => 0], 'credits'],
    'negative credits' => [['credits' => -50], 'credits'],
    'over the cap' => [['credits' => 1_000_001], 'credits'],
]);

it('grants plan time as an expiring manual grant and logs it', function () {
    $workspace = billedWorkspace();
    $plan = Plan::factory()->create(['name' => 'Pro', 'credits_monthly' => 50_000]);
    signInAsSuperAdmin(superAdmin());

    $this->post(route('super-admin.workspaces.plan-time.grant', $workspace), [
        'plan_id' => $plan->id,
        'days' => 30,
        'reason' => 'Partner trial',
        'current_password' => 'password',
    ])->assertRedirect(route('super-admin.workspaces.show', $workspace));

    $grant = $workspace->planGrants()->sole();

    expect($grant->source)->toBe(PlanGrantSource::Manual)
        ->and($grant->status)->toBe(PlanGrantStatus::Active)
        ->and($grant->price_cents)->toBe(0)
        ->and($grant->expires_at->isSameDay(now()->addDays(30)))->toBeTrue()
        ->and($workspace->fresh()->currentPlan()->is($plan))->toBeTrue()
        ->and(SuperAdminAuditLog::query()->where('action', 'workspace.plan_time.granted')->exists())->toBeTrue();
});

it('refuses plan time for an inactive plan or an open-ended length', function (array $input, string $field) {
    $workspace = billedWorkspace();
    $plan = Plan::factory()->create();
    signInAsSuperAdmin(superAdmin());

    $this->post(route('super-admin.workspaces.plan-time.grant', $workspace), [
        'plan_id' => $plan->id,
        'days' => 30,
        'reason' => 'Partner trial',
        'current_password' => 'password',
        ...$input,
    ])->assertSessionHasErrorsIn('grantPlanTime', $field);

    expect($workspace->planGrants()->count())->toBe(0);
})->with([
    'inactive plan' => [fn () => ['plan_id' => Plan::factory()->create(['is_active' => false])->id], 'plan_id'],
    'unknown plan' => [['plan_id' => '00000000-0000-0000-0000-000000000000'], 'plan_id'],
    'too many days' => [['days' => 3651], 'days'],
    'no days' => [['days' => 0], 'days'],
]);

it('revokes an active manual grant and logs it', function () {
    $workspace = billedWorkspace();
    $grant = PlanGrant::factory()->forWorkspace($workspace)->forPlan(Plan::factory()->create())->active()
        ->create(['source' => PlanGrantSource::Manual, 'expires_at' => now()->addDays(10)]);
    signInAsSuperAdmin(superAdmin());

    $this->post(route('super-admin.workspaces.plan-grants.revoke', [$workspace, $grant]), [
        'reason' => 'Granted by mistake',
        'current_password' => 'password',
    ])->assertRedirect(route('super-admin.workspaces.show', $workspace));

    expect($grant->fresh()->status)->toBe(PlanGrantStatus::Revoked)
        ->and(SuperAdminAuditLog::query()->where('action', 'workspace.plan_time.revoked')->exists())->toBeTrue();
});

it('will not revoke a purchased grant from the panel', function () {
    $workspace = billedWorkspace();
    $grant = PlanGrant::factory()->forWorkspace($workspace)->forPlan(Plan::factory()->create())->active()
        ->create(['source' => PlanGrantSource::LifetimePurchase]);
    signInAsSuperAdmin(superAdmin());

    $this->post(route('super-admin.workspaces.plan-grants.revoke', [$workspace, $grant]), [
        'reason' => 'Trying anyway',
        'current_password' => 'password',
    ])->assertSessionHasErrorsIn('revokePlanGrant', 'reason');

    expect($grant->fresh()->status)->toBe(PlanGrantStatus::Active);
});

it('will not revoke another workspace\'s grant through this workspace', function () {
    $workspace = billedWorkspace();
    $grant = PlanGrant::factory()->forWorkspace(billedWorkspace())->forPlan(Plan::factory()->create())->active()
        ->create(['source' => PlanGrantSource::Manual]);
    signInAsSuperAdmin(superAdmin());

    $this->post(route('super-admin.workspaces.plan-grants.revoke', [$workspace, $grant]), [
        'reason' => 'Wrong workspace',
        'current_password' => 'password',
    ])->assertNotFound();

    expect($grant->fresh()->status)->toBe(PlanGrantStatus::Active);
});

it('offers no billing changes on a deleted workspace', function () {
    $workspace = billedWorkspace();
    $workspace->delete();
    signInAsSuperAdmin(superAdmin());

    $this->get(route('super-admin.workspaces.show', $workspace))->assertOk()->assertDontSee('Add top-up credits');

    $this->post(route('super-admin.workspaces.credits.grant', $workspace), [
        'credits' => 100,
        'reason' => 'Should not work',
        'current_password' => 'password',
    ])->assertNotFound();
});

it('shows the billing forms and grants on the workspace page', function () {
    $workspace = billedWorkspace();
    Plan::factory()->create(['name' => 'Scale']);
    signInAsSuperAdmin(superAdmin());

    $this->get(route('super-admin.workspaces.show', $workspace))
        ->assertOk()
        ->assertSee('Add top-up credits')
        ->assertSee('Grant plan time')
        ->assertSee('Scale')
        ->assertSee('data-confirm', false);
});

it('keeps billing actions behind the super admin sign-in', function () {
    $workspace = billedWorkspace();

    $this->post(route('super-admin.workspaces.credits.grant', $workspace), ['credits' => 100, 'reason' => 'Sneaky grant', 'current_password' => 'password'])
        ->assertRedirect(route('super-admin.login'));

    expect($workspace->fresh()->topup_credits)->toBe(0);
});
