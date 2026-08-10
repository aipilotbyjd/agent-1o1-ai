<?php

use App\Models\Billing\Plan;
use App\Models\User;
use App\Services\Workspaces\WorkspaceService;

function billingLoginTokens(string $email, string $password): array
{
    return test()->postJson('/api/v1/auth/login', [
        'email' => $email,
        'password' => $password,
    ])->json('data.tokens');
}

it('lists only active plans ordered by sort_order', function () {
    Plan::query()->delete();
    Plan::factory()->create(['slug' => 'inactive', 'is_active' => false, 'sort_order' => 0]);
    Plan::factory()->create(['slug' => 'second', 'is_active' => true, 'sort_order' => 2]);
    Plan::factory()->create(['slug' => 'first', 'is_active' => true, 'sort_order' => 1]);

    $owner = User::factory()->create(['password' => 'Password1!']);
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $tokens = billingLoginTokens($owner->email, 'Password1!');

    $response = test()->withToken($tokens['access_token'])
        ->getJson("/api/v1/workspaces/{$workspace->id}/billing/plans")
        ->assertOk();

    $slugs = collect($response->json('data.plans'))->pluck('slug');

    expect($slugs->all())->toBe(['first', 'second']);
});
