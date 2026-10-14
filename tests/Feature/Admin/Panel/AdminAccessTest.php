<?php

use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->withoutVite();
    Notification::fake();
});

it('sends guests to the sign-in page', function () {
    $this->get(route('panel.dashboard'))->assertRedirect(route('panel.login'));
    $this->get(route('panel.account'))->assertRedirect(route('panel.login'));
});

it('never lets a customer user in', function () {
    $this->actingAs(User::factory()->create(), 'web')
        ->get(route('panel.dashboard'))
        ->assertRedirect(route('panel.login'));

    Passport::actingAs(User::factory()->create());

    $this->get(route('panel.dashboard'))->assertRedirect(route('panel.login'));
});

it('does not accept customer credentials on the admin sign-in form', function () {
    $user = User::factory()->create(['password' => 'password']);

    $this->post(route('panel.login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors('email');
});

it('hides the panel from addresses outside the allowlist', function () {
    config(['admin_panel.allowed_ips' => ['10.0.0.0/8']]);

    $this->get(route('panel.login'))->assertNotFound();

    $this->withServerVariables(['REMOTE_ADDR' => '10.1.2.3'])->get(route('panel.login'))->assertOk();
});

it('sends strict security headers and its own session cookie', function () {
    $response = $this->get(route('panel.login'))->assertOk();

    $csp = $response->headers->get('Content-Security-Policy');

    expect($csp)->toContain("script-src 'self' 'nonce-")
        ->toContain("frame-ancestors 'none'")
        ->toContain("form-action 'self'")
        ->and($response->headers->get('X-Frame-Options'))->toBe('DENY')
        ->and($response->headers->get('X-Robots-Tag'))->toBe('noindex, nofollow')
        ->and($response->headers->get('Cache-Control'))->toContain('no-store');

    $cookie = collect($response->headers->getCookies())->first(fn ($cookie) => $cookie->getName() === 'admin_session');

    expect($cookie)->not->toBeNull()
        ->and($cookie->getSameSite())->toBe('strict')
        ->and($cookie->isHttpOnly())->toBeTrue()
        ->and($cookie->getDomain())->toBeNull();
});

it('signs out an admin who goes idle', function () {
    signInToPanel(panelAdmin());

    $this->get(route('panel.dashboard'))->assertOk();

    $this->travel(config('admin_panel.idle_timeout') + 1)->minutes();

    $this->get(route('panel.dashboard'))->assertRedirect(route('panel.login'));
    $this->assertGuest('admin');
});

it('signs out an admin after the absolute lifetime even while active', function () {
    config(['admin_panel.absolute_lifetime' => 60, 'admin_panel.idle_timeout' => 30]);
    signInToPanel(panelAdmin());

    foreach (range(1, 3) as $step) {
        $this->travel(25)->minutes();
        $response = $this->get(route('panel.dashboard'));
    }

    $response->assertRedirect(route('panel.login'));
    $this->assertGuest('admin');
});

it('ends the session when the admin is disabled', function () {
    $admin = panelAdmin();
    signInToPanel($admin);

    $this->artisan('admin:disable', ['email' => $admin->email])->assertSuccessful();

    $this->get(route('panel.dashboard'))->assertRedirect(route('panel.login'));
    $this->assertGuest('admin');
});

it('ends the session when two-factor is reset', function () {
    $admin = panelAdmin();
    signInToPanel($admin);

    $this->artisan('admin:reset-two-factor', ['email' => $admin->email])
        ->expectsConfirmation("Reset two-factor authentication for {$admin->email}?", 'yes')
        ->assertSuccessful();

    $this->get(route('panel.dashboard'))->assertRedirect(route('panel.login'));
    $this->assertGuest('admin');
});

it('ends the session when the password is reset elsewhere', function () {
    $admin = panelAdmin();
    signInToPanel($admin);

    $admin->forceFill(['password' => 'Another-Str0ng-Pass!'])->save();

    $this->get(route('panel.dashboard'))->assertRedirect(route('panel.login'));
    $this->assertGuest('admin');
});
