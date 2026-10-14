<?php

use App\Models\SuperAdmin\SuperAdmin;
use App\Models\SuperAdmin\SuperAdminAuditLog;
use App\Services\SuperAdmin\SuperAdminAuthenticator;
use App\Services\Auth\LoginThrottle;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->withoutVite();
    Notification::fake();
});

it('shows the sign-in form under /jd', function () {
    $this->get('/jd/login')->assertOk()->assertSee('Sign in');
});

it('does not sign in on a correct password alone', function () {
    $superAdmin = superAdmin();

    $this->post(route('super-admin.login.store'), ['email' => $superAdmin->email, 'password' => 'password'])
        ->assertRedirect(route('super-admin.two-factor.challenge'));

    $this->assertGuest('super_admin');
    expect(session(SuperAdminAuthenticator::PENDING_ID))->toBe($superAdmin->id);
});

it('sends a super admin without two-factor to enrollment, still signed out', function () {
    $superAdmin = SuperAdmin::factory()->create();

    $this->post(route('super-admin.login.store'), ['email' => $superAdmin->email, 'password' => 'password'])
        ->assertRedirect(route('super-admin.two-factor.setup'));

    $this->assertGuest('super_admin');
});

it('gives the same answer for a wrong password and an unknown email', function () {
    $superAdmin = superAdmin();

    $this->post(route('super-admin.login.store'), ['email' => $superAdmin->email, 'password' => 'wrong-password'])
        ->assertSessionHasErrors(['email' => 'These credentials do not match our records.']);

    $this->post(route('super-admin.login.store'), ['email' => 'nobody@example.com', 'password' => 'password'])
        ->assertSessionHasErrors(['email' => 'These credentials do not match our records.']);

    $this->assertGuest('super_admin');
    expect(session(SuperAdminAuthenticator::PENDING_ID))->toBeNull()
        ->and(SuperAdminAuditLog::query()->where('action', 'super_admin.login.failed')->count())->toBe(2);
});

it('refuses a disabled admin with the generic error', function () {
    $superAdmin = superAdmin(['disabled_at' => now()]);

    $this->post(route('super-admin.login.store'), ['email' => $superAdmin->email, 'password' => 'password'])
        ->assertSessionHasErrors(['email' => 'These credentials do not match our records.']);

    expect(session(SuperAdminAuthenticator::PENDING_ID))->toBeNull();
});

it('locks the account after repeated wrong passwords, even for the right one', function () {
    config(['auth.lockout.max_attempts' => 3]);
    $superAdmin = superAdmin();

    foreach (range(1, 3) as $attempt) {
        $this->post(route('super-admin.login.store'), ['email' => $superAdmin->email, 'password' => 'wrong-password']);
    }

    $this->post(route('super-admin.login.store'), ['email' => $superAdmin->email, 'password' => 'password'])
        ->assertSessionHasErrors('email');

    expect(session('errors')->first('email'))->toStartWith('Too many failed sign-in attempts');
    expect(session(SuperAdminAuthenticator::PENDING_ID))->toBeNull();
});

it('does not let a customer lockout lock the super admin account with the same email', function () {
    config(['auth.lockout.max_attempts' => 1]);
    $superAdmin = superAdmin(['email' => 'shared@example.com']);

    app(LoginThrottle::class)->recordFailure('shared@example.com');

    $this->post(route('super-admin.login.store'), ['email' => $superAdmin->email, 'password' => 'password'])
        ->assertRedirect(route('super-admin.two-factor.challenge'));
});

it('rate limits the sign-in form', function () {
    foreach (range(1, 5) as $attempt) {
        $this->post(route('super-admin.login.store'), ['email' => "x{$attempt}@example.com", 'password' => 'nope']);
    }

    $this->post(route('super-admin.login.store'), ['email' => 'x6@example.com', 'password' => 'nope'])->assertTooManyRequests();
});

it('signs out and ends the session', function () {
    $superAdmin = superAdmin();
    signInAsSuperAdmin($superAdmin);
    $this->assertAuthenticatedAs($superAdmin, 'super_admin');

    $this->post(route('super-admin.logout'))->assertRedirect(route('super-admin.login'));

    $this->assertGuest('super_admin');
    $this->get(route('super-admin.dashboard'))->assertRedirect(route('super-admin.login'));
    expect(SuperAdminAuditLog::query()->where('action', 'super_admin.logout')->exists())->toBeTrue();
});

it('sends a signed-in admin away from the login page', function () {
    signInAsSuperAdmin(superAdmin());

    $this->get(route('super-admin.login'))->assertRedirect(route('super-admin.dashboard'));
});
