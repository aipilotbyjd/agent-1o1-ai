<?php

use App\Enums\Billing\CreditTransactionType;
use App\Enums\Billing\PlanGrantSource;
use App\Models\Billing\CreditTransaction;
use App\Models\Billing\Plan;
use App\Models\Billing\PlanGrant;
use App\Models\Billing\Subscription;
use App\Models\Workspaces\Workspace;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->withoutVite();
    Notification::fake();
});

function billingWorkspace(string $name): Workspace
{
    return referralUser(['name' => $name])->ownedWorkspaces()->first();
}

/**
 * @param  array<string, mixed>  $attributes
 */
function billingSubscription(Workspace $workspace, Plan $plan, string $status, array $attributes = []): Subscription
{
    return Subscription::query()->forceCreate([
        'workspace_id' => $workspace->id,
        'plan_id' => $plan->id,
        'type' => 'default',
        'stripe_id' => 'sub_'.Str::random(14),
        'stripe_status' => $status,
        ...$attributes,
    ]);
}

function billingCharge(Workspace $workspace, int $credits, CreditTransactionType $type = CreditTransactionType::NodeRun): CreditTransaction
{
    return CreditTransaction::query()->create([
        'workspace_id' => $workspace->id,
        'source_type' => $type,
        'source_id' => (string) Str::uuid(),
        'credits' => $credits,
    ]);
}

it('keeps every billing and system page behind the super admin sign-in', function (string $routeName) {
    $this->get(route($routeName))->assertRedirect(route('super-admin.login'));
})->with([
    'super-admin.billing.subscriptions.index',
    'super-admin.billing.credits.index',
    'super-admin.billing.plan-grants.index',
    'super-admin.billing.plans.index',
    'super-admin.system.queues.index',
    'super-admin.system.failed-jobs.index',
]);

it('lists subscriptions with status counts and filters', function () {
    $plan = Plan::factory()->create(['name' => 'Starter']);
    billingSubscription(billingWorkspace('Active Co'), $plan, 'active');
    billingSubscription(billingWorkspace('Late Co'), $plan, 'past_due');
    signInAsSuperAdmin(superAdmin());

    $this->get(route('super-admin.billing.subscriptions.index'))
        ->assertOk()
        ->assertSee("Active Co's Workspace")
        ->assertSee("Late Co's Workspace");

    $this->get(route('super-admin.billing.subscriptions.index', ['status' => 'past_due']))
        ->assertSee("Late Co's Workspace")
        ->assertDontSee("Active Co's Workspace");
});

it('finds trials ending soon', function () {
    $plan = Plan::factory()->create();
    billingSubscription(billingWorkspace('Soon'), $plan, 'trialing', ['trial_ends_at' => now()->addDays(3)]);
    billingSubscription(billingWorkspace('Later'), $plan, 'trialing', ['trial_ends_at' => now()->addDays(20)]);
    signInAsSuperAdmin(superAdmin());

    $this->get(route('super-admin.billing.subscriptions.index', ['trial_ending' => 1]))
        ->assertSee("Soon's Workspace")
        ->assertDontSee("Later's Workspace");
});

it('rejects an unknown subscription status', function () {
    signInAsSuperAdmin(superAdmin());

    $this->get(route('super-admin.billing.subscriptions.index', ['status' => 'happy']))->assertSessionHasErrors('status');
});

it('shows the credit ledger with totals for the filter', function () {
    $acme = billingWorkspace('Acme');
    $other = billingWorkspace('Other');
    billingCharge($acme, 4321);
    billingCharge($acme, 1111, CreditTransactionType::AssistantTurn);
    billingCharge($other, 9876);
    signInAsSuperAdmin(superAdmin());

    $this->get(route('super-admin.billing.credits.index', ['workspace' => $acme->id]))
        ->assertOk()
        ->assertSee("Acme's Workspace")
        ->assertDontSee("Other's Workspace")
        ->assertSee('5,432')
        ->assertDontSee('9,876');

    $this->get(route('super-admin.billing.credits.index', ['workspace' => $acme->id, 'type' => 'assistant_turn']))
        ->assertSee('1,111')
        ->assertDontSee('4,321');
});

it('lists plan grants by source', function () {
    $plan = Plan::factory()->create(['name' => 'Pro']);
    PlanGrant::factory()->forWorkspace(billingWorkspace('Lifetime'))->forPlan($plan)->active()->create(['source' => PlanGrantSource::LifetimePurchase]);
    PlanGrant::factory()->forWorkspace(billingWorkspace('Comp'))->forPlan($plan)->active()->create(['source' => PlanGrantSource::Manual]);
    signInAsSuperAdmin(superAdmin());

    $this->get(route('super-admin.billing.plan-grants.index', ['source' => 'manual']))
        ->assertOk()
        ->assertSee("Comp's Workspace")
        ->assertDontSee("Lifetime's Workspace");
});

it('shows the plan catalogue with live counts', function () {
    $plan = Plan::factory()->create([
        'name' => 'Growth',
        'limits' => ['workflows' => 25, 'agents' => -1],
        'features' => ['git_sync' => true, 'public_pages' => false],
    ]);
    billingSubscription(billingWorkspace('Paying'), $plan, 'active');
    billingSubscription(billingWorkspace('Gone'), $plan, 'canceled');
    PlanGrant::factory()->forWorkspace(billingWorkspace('Granted'))->forPlan($plan)->active()->create();
    signInAsSuperAdmin(superAdmin());

    $response = $this->get(route('super-admin.billing.plans.index'))->assertOk()->assertSee('Growth')->assertSee('$29.00');

    $response->assertSeeInOrder(['Active subscriptions', '1', 'Active grants', '1'])
        ->assertSee('Unlimited')
        ->assertSee('git sync')
        ->assertDontSee('public pages');
});
