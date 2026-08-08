<?php

use App\Models\User;

it('registers a user, creates their workspace, and issues tokens', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'Password1!',
        'password_confirmation' => 'Password1!',
    ]);

    $response->assertCreated();
    $response->assertJsonStructure([
        'user' => ['id', 'name', 'email'],
        'tokens' => ['access_token', 'refresh_token', 'expires_in', 'token_type'],
    ]);

    $user = User::query()->where('email', 'jane@example.com')->firstOrFail();

    expect($user->ownedWorkspaces()->count())->toBe(1);
    expect($user->workspaces()->count())->toBe(1);
});

it('rejects registration with a mismatched password confirmation', function () {
    $this->postJson('/api/v1/auth/register', [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'Password1!',
        'password_confirmation' => 'nope',
    ])->assertUnprocessable();
});
