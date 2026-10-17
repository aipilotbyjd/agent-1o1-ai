<?php

use App\Models\SuperAdmin\SuperAdminAuditLog;
use App\Models\User;
use App\Services\Auth\AuthService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Token;

/*
| Runs the real password and social-exchange grants, so it brings its own
| Passport clients instead of relying on PASSPORT_* values in the env.
*/
beforeEach(function () {
    $this->withoutVite();
    Notification::fake();

    $id = (string) Str::uuid();

    Client::query()->create([
        'id' => $id,
        'name' => 'Suspension test password client',
        'secret' => 'suspension-test-secret',
        'redirect_uris' => [],
        'grant_types' => ['password', 'refresh_token', 'social_exchange'],
        'revoked' => false,
    ]);

    config(['passport.password_client_id' => $id, 'passport.password_client_secret' => 'suspension-test-secret']);

    app(ClientRepository::class)->createPersonalAccessGrantClient('Suspension test personal client', 'users');
});

function suspendThroughPanel(User $user): void
{
    test()->post(route('super-admin.users.suspend', $user), ['reason' => 'Chargeback fraud', 'current_password' => 'password']);
}

it('suspends a user, ends their sessions and logs it', function () {
    $user = User::factory()->create();
    $token = $user->createToken('device')->token;
    $admin = superAdmin();
    signInAsSuperAdmin($admin);

    $this->post(route('super-admin.users.suspend', $user), ['reason' => 'Chargeback fraud', 'current_password' => 'password'])
        ->assertRedirect(route('super-admin.users.show', $user));

    $user->refresh();

    expect($user->isSuspended())->toBeTrue()
        ->and($user->suspension_reason)->toBe('Chargeback fraud')
        ->and(Token::find($token->id)->revoked)->toBeTrue();

    $entry = SuperAdminAuditLog::query()->where('action', 'user.suspended')->sole();

    expect($entry->super_admin_id)->toBe($admin->id)
        ->and($entry->after)->toMatchArray(['suspended' => true, 'reason' => 'Chargeback fraud', 'tokens_revoked' => 1]);
});

it('needs a reason and the super admin password to suspend', function (array $input, string $field) {
    $user = User::factory()->create();
    signInAsSuperAdmin(superAdmin());

    $this->post(route('super-admin.users.suspend', $user), ['reason' => 'Chargeback fraud', 'current_password' => 'password', ...$input])
        ->assertSessionHasErrorsIn('suspend', $field);

    expect($user->fresh()->isSuspended())->toBeFalse();
})->with([
    'wrong password' => [['current_password' => 'wrong'], 'current_password'],
    'no reason' => [['reason' => ''], 'reason'],
]);

it('blocks password sign-in for a suspended user, only after the right password', function () {
    $user = User::factory()->create(['email' => 'blocked@acme.test', 'password' => 'Secret-Pass-123!']);
    signInAsSuperAdmin(superAdmin());
    suspendThroughPanel($user);

    $this->postJson('/api/v1/auth/login', ['email' => 'blocked@acme.test', 'password' => 'Secret-Pass-123!'])
        ->assertStatus(422)
        ->assertJsonPath('errors.email.0', AuthService::SUSPENDED_MESSAGE);

    $this->postJson('/api/v1/auth/login', ['email' => 'blocked@acme.test', 'password' => 'wrong-password'])
        ->assertStatus(422)
        ->assertJsonPath('errors.email.0', 'These credentials do not match our records.');
});

it('refuses a suspended user at the token endpoint itself', function () {
    $user = User::factory()->create(['email' => 'direct@acme.test', 'password' => 'Secret-Pass-123!']);
    signInAsSuperAdmin(superAdmin());
    suspendThroughPanel($user);

    $this->post('/oauth/token', [
        'grant_type' => 'password',
        'client_id' => config('passport.password_client_id'),
        'client_secret' => config('passport.password_client_secret'),
        'username' => 'direct@acme.test',
        'password' => 'Secret-Pass-123!',
        'scope' => '',
    ])->assertStatus(400);
});

it('blocks the social sign-in exchange for a suspended user', function () {
    $user = User::factory()->create();
    signInAsSuperAdmin(superAdmin());
    suspendThroughPanel($user);

    $code = app(AuthService::class)->issueSocialExchangeCode($user->fresh(), 'github');

    $this->postJson('/api/v1/auth/social/exchange', ['code' => $code])
        ->assertStatus(422)
        ->assertJsonPath('errors.code.0', AuthService::SUSPENDED_MESSAGE);
});

it('rejects an access token that is still around for a suspended user', function () {
    $user = User::factory()->create();
    $plainToken = $user->createToken('device')->accessToken;

    $this->withToken($plainToken)->getJson('/api/v1/user')->assertOk();

    $user->forceFill(['suspended_at' => now()])->save();
    Auth::forgetGuards();

    $this->withToken($plainToken)->getJson('/api/v1/user')
        ->assertForbidden()
        ->assertJsonPath('message', AuthService::SUSPENDED_MESSAGE);
});

it('lifts a suspension so the user can sign in again', function () {
    $user = User::factory()->create(['email' => 'back@acme.test', 'password' => 'Secret-Pass-123!']);
    signInAsSuperAdmin(superAdmin());
    suspendThroughPanel($user);

    $this->post(route('super-admin.users.unsuspend', $user), ['current_password' => 'password'])
        ->assertRedirect(route('super-admin.users.show', $user));

    expect($user->fresh()->isSuspended())->toBeFalse()
        ->and($user->fresh()->suspension_reason)->toBeNull()
        ->and(SuperAdminAuditLog::query()->where('action', 'user.unsuspended')->exists())->toBeTrue();

    $this->postJson('/api/v1/auth/login', ['email' => 'back@acme.test', 'password' => 'Secret-Pass-123!'])
        ->assertOk()
        ->assertJsonStructure(['data' => ['tokens' => ['access_token']]]);
});

it('never shows the suspension reason through the customer API', function () {
    $user = User::factory()->create();
    $user->forceFill(['suspension_reason' => 'Internal note: fraud ring'])->save();
    $plainToken = $user->createToken('device')->accessToken;

    $this->withToken($plainToken)->getJson('/api/v1/user')
        ->assertOk()
        ->assertDontSee('Internal note: fraud ring');
});

it('shows and filters suspended users in the panel', function () {
    $suspended = User::factory()->create(['email' => 'suspended@acme.test']);
    User::factory()->create(['email' => 'fine@acme.test']);
    signInAsSuperAdmin(superAdmin());
    suspendThroughPanel($suspended);

    $this->get(route('super-admin.users.index', ['suspended' => 'yes']))
        ->assertOk()
        ->assertSee('suspended@acme.test')
        ->assertDontSee('fine@acme.test');

    $this->get(route('super-admin.users.show', $suspended))
        ->assertOk()
        ->assertSee('Chargeback fraud')
        ->assertSee('Lift suspension');
});
