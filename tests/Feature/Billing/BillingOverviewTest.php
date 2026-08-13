<?php

use App\Actions\Billing\DeductCreditsAction;
use App\Enums\Billing\CreditTransactionType;
use App\Models\Billing\Plan;
use App\Models\Billing\Subscription;
use App\Models\User;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Passport\Passport;

it('returns null subscription and the current usage period when unsubscribed', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    Passport::actingAs($owner);

    $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/billing");

    $response->assertOk();
    expect($response->json('data.subscription'))->toBeNull();
    expect($response->json('data.usage_period.credits_used'))->toBe(0);
});

it('returns the subscription with its plan when subscribed', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $plan = Plan::factory()->create(['slug' => 'pro']);

    Subscription::query()->create([
        'workspace_id' => $workspace->id,
        'plan_id' => $plan->id,
        'type' => 'default',
        'stripe_id' => 'sub_test_overview',
        'stripe_status' => 'active',
        'stripe_price' => 'price_test',
    ]);

    Passport::actingAs($owner);

    $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/billing");

    $response->assertOk();
    expect($response->json('data.subscription.plan.slug'))->toBe('pro');
});

it('lists credit transaction history newest first, paginated', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    app(DeductCreditsAction::class)->execute($workspace, CreditTransactionType::NodeRun, 1, 3, 'first');
    app(DeductCreditsAction::class)->execute($workspace, CreditTransactionType::NodeRun, 2, 5, 'second');

    Passport::actingAs($owner);

    $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/billing/credits");

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(2);
    expect($response->json('data.0.reason'))->toBe('second');
    expect($response->json('meta.total'))->toBe(2);
});
