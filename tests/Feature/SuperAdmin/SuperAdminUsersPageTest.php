<?php

use App\Enums\Auth\AuthEvent;
use App\Models\Auth\AuthEventLog;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->withoutVite();
    Notification::fake();
});

it('keeps every core page behind the super admin sign-in', function (string $routeName, bool $needsId) {
    $url = $needsId ? route($routeName, fake()->uuid()) : route($routeName);

    $this->get($url)->assertRedirect(route('super-admin.login'));
})->with([
    ['super-admin.users.index', false],
    ['super-admin.users.show', true],
    ['super-admin.workspaces.index', false],
    ['super-admin.workspaces.show', true],
    ['super-admin.runs.index', false],
    ['super-admin.runs.show', true],
    ['super-admin.audit-log.index', false],
]);

it('lists and searches users', function () {
    User::factory()->create(['email' => 'alice@acme.test', 'name' => 'Alice']);
    User::factory()->create(['email' => 'bob@other.test', 'name' => 'Bob']);
    signInAsSuperAdmin(superAdmin());

    $this->get(route('super-admin.users.index'))->assertOk()->assertSee('alice@acme.test')->assertSee('bob@other.test');

    $this->get(route('super-admin.users.index', ['q' => 'acme']))
        ->assertOk()
        ->assertSee('alice@acme.test')
        ->assertDontSee('bob@other.test');
});

it('finds a user by exact ID', function () {
    $user = User::factory()->create(['email' => 'byid@acme.test']);
    User::factory()->create(['email' => 'other@acme.test']);
    signInAsSuperAdmin(superAdmin());

    $this->get(route('super-admin.users.index', ['q' => $user->id]))
        ->assertSee('byid@acme.test')
        ->assertDontSee('other@acme.test');
});

it('treats LIKE wildcards in a search as plain text', function () {
    User::factory()->create(['email' => 'alice@acme.test']);
    signInAsSuperAdmin(superAdmin());

    $this->get(route('super-admin.users.index', ['q' => '%']))->assertOk()->assertDontSee('alice@acme.test');
    $this->get(route('super-admin.users.index', ['q' => '_']))->assertOk()->assertDontSee('alice@acme.test');
});

it('filters users by two-factor status', function () {
    $withTwoFactor = User::factory()->create(['email' => 'secure@acme.test']);
    $withTwoFactor->forceFill(['two_factor_confirmed_at' => now()])->save();
    User::factory()->create(['email' => 'plain@acme.test']);
    signInAsSuperAdmin(superAdmin());

    $this->get(route('super-admin.users.index', ['two_factor' => 'enabled']))
        ->assertSee('secure@acme.test')
        ->assertDontSee('plain@acme.test');
});

it('rejects unknown filter values', function () {
    signInAsSuperAdmin(superAdmin());

    $this->get(route('super-admin.users.index', ['two_factor' => 'maybe']))->assertSessionHasErrors('two_factor');
});

it('shows a user with their workspaces and sign-in history', function () {
    $user = referralUser(['email' => 'carol@acme.test', 'name' => 'Carol']);
    AuthEventLog::factory()->for($user)->event(AuthEvent::LoggedIn)->create(['ip_address' => '203.0.113.9']);
    signInAsSuperAdmin(superAdmin());

    $this->get(route('super-admin.users.show', $user))
        ->assertOk()
        ->assertSee('carol@acme.test')
        ->assertSee("Carol's Workspace")
        ->assertSee('logged in')
        ->assertSee('203.0.113.9');
});

it('never shows a customer password hash or two-factor secret', function () {
    $user = User::factory()->create();
    $user->forceFill(['two_factor_secret' => 'JBSWY3DPEHPK3PXP', 'two_factor_confirmed_at' => now()])->save();
    signInAsSuperAdmin(superAdmin());

    $this->get(route('super-admin.users.show', $user))
        ->assertOk()
        ->assertDontSee($user->password)
        ->assertDontSee('JBSWY3DPEHPK3PXP');
});

it('answers 404 for a missing or malformed user ID', function () {
    signInAsSuperAdmin(superAdmin());

    $this->get(route('super-admin.users.show', fake()->uuid()))->assertNotFound();
    $this->get('/jd/users/not-a-uuid')->assertNotFound();
});
