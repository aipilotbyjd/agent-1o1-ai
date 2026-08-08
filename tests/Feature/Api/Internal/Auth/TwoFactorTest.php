<?php

use App\Models\User;
use PragmaRX\Google2FA\Google2FA;

function currentOtpFor(string $secret): string
{
    return app(Google2FA::class)->getCurrentOtp($secret);
}

it('enables and confirms two-factor, returning recovery codes', function () {
    $user = User::factory()->create(['password' => 'Password1!']);

    $enable = $this->actingAs($user, 'api')
        ->postJson('/api/v1/auth/2fa/enable')
        ->assertOk()
        ->assertJsonStructure(['secret', 'otpauth_url'])
        ->json();

    $code = currentOtpFor($enable['secret']);

    $this->actingAs($user, 'api')
        ->postJson('/api/v1/auth/2fa/confirm', ['code' => $code])
        ->assertOk()
        ->assertJsonStructure(['recovery_codes'])
        ->assertJsonCount(8, 'recovery_codes');

    expect($user->fresh()->hasTwoFactorEnabled())->toBeTrue();
});

it('rejects an invalid confirmation code', function () {
    $user = User::factory()->create(['password' => 'Password1!']);

    $this->actingAs($user, 'api')->postJson('/api/v1/auth/2fa/enable');

    $this->actingAs($user, 'api')
        ->postJson('/api/v1/auth/2fa/confirm', ['code' => '000000'])
        ->assertStatus(422);
});

it('returns a two-factor challenge on login instead of tokens, then completes it', function () {
    $user = User::factory()->create([
        'email' => 'jane@example.com',
        'password' => 'Password1!',
    ]);

    $enable = $this->actingAs($user, 'api')
        ->postJson('/api/v1/auth/2fa/enable')
        ->json();

    $this->actingAs($user, 'api')
        ->postJson('/api/v1/auth/2fa/confirm', ['code' => currentOtpFor($enable['secret'])]);

    $login = $this->postJson('/api/v1/auth/login', [
        'email' => 'jane@example.com',
        'password' => 'Password1!',
    ])->assertOk()
        ->assertJsonStructure(['two_factor_challenge'])
        ->json();

    $verify = $this->postJson('/api/v1/auth/2fa/verify', [
        'challenge_token' => $login['two_factor_challenge'],
        'code' => currentOtpFor($enable['secret']),
    ])->assertOk()
        ->assertJsonStructure([
            'user' => ['id', 'email'],
            'tokens' => ['access_token', 'refresh_token', 'expires_in', 'token_type'],
        ]);

    $this->withToken($verify->json('tokens.access_token'))
        ->getJson('/api/v1/user')
        ->assertOk()
        ->assertJsonPath('user.email', $user->email);
});

it('rejects an invalid two-factor verification code', function () {
    $user = User::factory()->create([
        'email' => 'jane@example.com',
        'password' => 'Password1!',
    ]);

    $enable = $this->actingAs($user, 'api')
        ->postJson('/api/v1/auth/2fa/enable')
        ->json();

    $this->actingAs($user, 'api')
        ->postJson('/api/v1/auth/2fa/confirm', ['code' => currentOtpFor($enable['secret'])]);

    $login = $this->postJson('/api/v1/auth/login', [
        'email' => 'jane@example.com',
        'password' => 'Password1!',
    ])->json();

    $this->postJson('/api/v1/auth/2fa/verify', [
        'challenge_token' => $login['two_factor_challenge'],
        'code' => '000000',
    ])->assertStatus(422);
});

it('disables two-factor and login goes straight to tokens again', function () {
    $user = User::factory()->create([
        'email' => 'jane@example.com',
        'password' => 'Password1!',
    ]);

    $enable = $this->actingAs($user, 'api')
        ->postJson('/api/v1/auth/2fa/enable')
        ->json();

    $this->actingAs($user, 'api')
        ->postJson('/api/v1/auth/2fa/confirm', ['code' => currentOtpFor($enable['secret'])]);

    $this->actingAs($user, 'api')
        ->postJson('/api/v1/auth/2fa/disable', ['current_password' => 'Password1!'])
        ->assertNoContent();

    $this->postJson('/api/v1/auth/login', [
        'email' => 'jane@example.com',
        'password' => 'Password1!',
    ])->assertOk()->assertJsonStructure(['user', 'tokens']);
});
