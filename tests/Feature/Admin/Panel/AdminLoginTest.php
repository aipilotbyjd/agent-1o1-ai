<?php

use App\Models\Admin\Admin;
use App\Models\Admin\AdminAuditLog;
use App\Services\Admin\AdminAuthenticator;
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
    $admin = panelAdmin();

    $this->post(route('panel.login.store'), ['email' => $admin->email, 'password' => 'password'])
        ->assertRedirect(route('panel.two-factor.challenge'));

    $this->assertGuest('admin');
    expect(session(AdminAuthenticator::PENDING_ID))->toBe($admin->id);
});

it('sends an admin without two-factor to enrollment, still signed out', function () {
    $admin = Admin::factory()->create();

    $this->post(route('panel.login.store'), ['email' => $admin->email, 'password' => 'password'])
        ->assertRedirect(route('panel.two-factor.setup'));

    $this->assertGuest('admin');
});

it('gives the same answer for a wrong password and an unknown email', function () {
    $admin = panelAdmin();

    $this->post(route('panel.login.store'), ['email' => $admin->email, 'password' => 'wrong-password'])
        ->assertSessionHasErrors(['email' => 'These credentials do not match our records.']);

    $this->post(route('panel.login.store'), ['email' => 'nobody@example.com', 'password' => 'password'])
        ->assertSessionHasErrors(['email' => 'These credentials do not match our records.']);

    $this->assertGuest('admin');
    expect(session(AdminAuthenticator::PENDING_ID))->toBeNull()
        ->and(AdminAuditLog::query()->where('action', 'admin.login.failed')->count())->toBe(2);
});

it('refuses a disabled admin with the generic error', function () {
    $admin = panelAdmin(['disabled_at' => now()]);

    $this->post(route('panel.login.store'), ['email' => $admin->email, 'password' => 'password'])
        ->assertSessionHasErrors(['email' => 'These credentials do not match our records.']);

    expect(session(AdminAuthenticator::PENDING_ID))->toBeNull();
});

it('locks the account after repeated wrong passwords, even for the right one', function () {
    config(['auth.lockout.max_attempts' => 3]);
    $admin = panelAdmin();

    foreach (range(1, 3) as $attempt) {
        $this->post(route('panel.login.store'), ['email' => $admin->email, 'password' => 'wrong-password']);
    }

    $this->post(route('panel.login.store'), ['email' => $admin->email, 'password' => 'password'])
        ->assertSessionHasErrors('email');

    expect(session('errors')->first('email'))->toStartWith('Too many failed sign-in attempts');
    expect(session(AdminAuthenticator::PENDING_ID))->toBeNull();
});

it('does not let a customer lockout lock the admin account with the same email', function () {
    config(['auth.lockout.max_attempts' => 1]);
    $admin = panelAdmin(['email' => 'shared@example.com']);

    app(LoginThrottle::class)->recordFailure('shared@example.com');

    $this->post(route('panel.login.store'), ['email' => $admin->email, 'password' => 'password'])
        ->assertRedirect(route('panel.two-factor.challenge'));
});

it('rate limits the sign-in form', function () {
    foreach (range(1, 5) as $attempt) {
        $this->post(route('panel.login.store'), ['email' => "x{$attempt}@example.com", 'password' => 'nope']);
    }

    $this->post(route('panel.login.store'), ['email' => 'x6@example.com', 'password' => 'nope'])->assertTooManyRequests();
});

it('signs out and ends the session', function () {
    $admin = panelAdmin();
    signInToPanel($admin);
    $this->assertAuthenticatedAs($admin, 'admin');

    $this->post(route('panel.logout'))->assertRedirect(route('panel.login'));

    $this->assertGuest('admin');
    $this->get(route('panel.dashboard'))->assertRedirect(route('panel.login'));
    expect(AdminAuditLog::query()->where('action', 'admin.logout')->exists())->toBeTrue();
});

it('sends a signed-in admin away from the login page', function () {
    signInToPanel(panelAdmin());

    $this->get(route('panel.login'))->assertRedirect(route('panel.dashboard'));
});
