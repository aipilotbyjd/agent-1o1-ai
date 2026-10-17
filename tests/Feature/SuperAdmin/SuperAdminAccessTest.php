<?php

use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->withoutVite();
    Notification::fake();
});

it('sends guests to the sign-in page', function () {
    $this->get(route('super-admin.dashboard'))->assertRedirect(route('super-admin.login'));
    $this->get(route('super-admin.account'))->assertRedirect(route('super-admin.login'));
});

it('never lets a customer user in', function () {
    $this->actingAs(User::factory()->create(), 'web')
        ->get(route('super-admin.dashboard'))
        ->assertRedirect(route('super-admin.login'));

    Passport::actingAs(User::factory()->create());

    $this->get(route('super-admin.dashboard'))->assertRedirect(route('super-admin.login'));
});

it('does not accept customer credentials on the super admin sign-in form', function () {
    $user = User::factory()->create(['password' => 'password']);

    $this->post(route('super-admin.login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors('email');
});

it('hides the panel from addresses outside the allowlist', function () {
    config(['super_admin.allowed_ips' => ['10.0.0.0/8']]);

    $this->get(route('super-admin.login'))->assertNotFound();

    $this->withServerVariables(['REMOTE_ADDR' => '10.1.2.3'])->get(route('super-admin.login'))->assertOk();
});

it('sends strict security headers and its own session cookie', function () {
    $response = $this->get(route('super-admin.login'))->assertOk();

    $csp = $response->headers->get('Content-Security-Policy');

    expect($csp)->toContain("script-src 'self' 'nonce-")
        ->toContain("frame-ancestors 'none'")
        ->toContain("form-action 'self'")
        ->and($response->headers->get('X-Frame-Options'))->toBe('DENY')
        ->and($response->headers->get('X-Robots-Tag'))->toBe('noindex, nofollow')
        ->and($response->headers->get('Cache-Control'))->toContain('no-store');

    $cookie = collect($response->headers->getCookies())->first(fn ($cookie) => $cookie->getName() === 'super_admin_session');

    expect($cookie)->not->toBeNull()
        ->and($cookie->getSameSite())->toBe('strict')
        ->and($cookie->isHttpOnly())->toBeTrue()
        ->and($cookie->getDomain())->toBeNull();
});

it('signs out a super admin who goes idle', function () {
    signInAsSuperAdmin(superAdmin());

    $this->get(route('super-admin.dashboard'))->assertOk();

    $this->travel(config('super_admin.idle_timeout') + 1)->minutes();

    $this->get(route('super-admin.dashboard'))->assertRedirect(route('super-admin.login'));
    $this->assertGuest('super_admin');
});

it('signs out a super admin after the absolute lifetime even while active', function () {
    config(['super_admin.absolute_lifetime' => 60, 'super_admin.idle_timeout' => 30]);
    signInAsSuperAdmin(superAdmin());

    foreach (range(1, 3) as $step) {
        $this->travel(25)->minutes();
        $response = $this->get(route('super-admin.dashboard'));
    }

    $response->assertRedirect(route('super-admin.login'));
    $this->assertGuest('super_admin');
});

it('ends the session when the super admin is disabled', function () {
    $superAdmin = superAdmin();
    signInAsSuperAdmin($superAdmin);

    $this->artisan('super-admin:disable', ['email' => $superAdmin->email])->assertSuccessful();

    $this->get(route('super-admin.dashboard'))->assertRedirect(route('super-admin.login'));
    $this->assertGuest('super_admin');
});

it('ends the session when two-factor is reset', function () {
    $superAdmin = superAdmin();
    signInAsSuperAdmin($superAdmin);

    $this->artisan('super-admin:reset-two-factor', ['email' => $superAdmin->email])
        ->expectsConfirmation("Reset two-factor authentication for {$superAdmin->email}?", 'yes')
        ->assertSuccessful();

    $this->get(route('super-admin.dashboard'))->assertRedirect(route('super-admin.login'));
    $this->assertGuest('super_admin');
});

it('ends the session when the password is reset elsewhere', function () {
    $superAdmin = superAdmin();
    signInAsSuperAdmin($superAdmin);

    $superAdmin->forceFill(['password' => 'Another-Str0ng-Pass!'])->save();

    $this->get(route('super-admin.dashboard'))->assertRedirect(route('super-admin.login'));
    $this->assertGuest('super_admin');
});
