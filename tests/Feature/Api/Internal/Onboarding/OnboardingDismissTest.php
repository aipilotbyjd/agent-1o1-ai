<?php

use App\Models\User;

function onboardingDismissTokens(string $email, string $password): array
{
    return test()->postJson('/api/v1/auth/login', [
        'email' => $email,
        'password' => $password,
    ])->json('data.tokens');
}

it('dismisses onboarding at any step', function () {
    $user = User::factory()->create(['password' => 'Password1!']);
    $tokens = onboardingDismissTokens($user->email, 'Password1!');

    $this->withToken($tokens['access_token'])
        ->postJson('/api/v1/user/dismiss-onboarding')
        ->assertOk()
        ->assertJsonPath('message', 'Onboarding dismissed.');

    $user->refresh();
    expect($user->onboarding_dismissed_at)->not->toBeNull();
});
