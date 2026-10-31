<?php

use App\Models\User;
use App\Services\Workspaces\WorkspaceService;

function onboardingDismissTokens(string $email, string $password): array
{
    return test()->postJson('/api/v1/auth/login', [
        'email' => $email,
        'password' => $password,
    ])->json('data.tokens');
}

it('dismisses onboarding once the user has a workspace', function () {
    $user = User::factory()->create(['password' => 'Password1!']);
    app(WorkspaceService::class)->create($user, ['name' => 'Acme']);
    $tokens = onboardingDismissTokens($user->email, 'Password1!');

    $this->withToken($tokens['access_token'])
        ->postJson('/api/v1/user/dismiss-onboarding')
        ->assertOk()
        ->assertJsonPath('message', 'Onboarding dismissed.');

    $user->refresh();
    expect($user->onboarding_dismissed_at)->not->toBeNull();
});

it('does not let a user without a workspace dismiss or complete onboarding', function () {
    $user = User::factory()->create(['password' => 'Password1!']);
    $tokens = onboardingDismissTokens($user->email, 'Password1!');

    $this->withToken($tokens['access_token'])
        ->postJson('/api/v1/user/dismiss-onboarding')
        ->assertUnprocessable();

    $this->withToken($tokens['access_token'])
        ->postJson('/api/v1/onboarding/complete')
        ->assertUnprocessable();

    expect($user->fresh()->onboarding_dismissed_at)->toBeNull()
        ->and($user->fresh()->onboarding_completed_at)->toBeNull();
});
