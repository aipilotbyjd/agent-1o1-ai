<?php

use App\Models\Admin\Admin;
use App\Models\Admin\AdminAuditLog;
use App\Notifications\Admin\AdminSignedInNotification;
use App\Services\Admin\AdminAuthenticator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->withoutVite();
    Notification::fake();
});

function enterPassword(Admin $admin): void
{
    test()->post(route('panel.login.store'), ['email' => $admin->email, 'password' => 'password']);
}

it('signs in with a valid authenticator code', function () {
    $admin = panelAdmin();
    enterPassword($admin);

    $this->post(route('panel.two-factor.verify'), ['code' => panelTotp($admin)])
        ->assertRedirect(route('panel.dashboard'));

    $this->assertAuthenticatedAs($admin, 'admin');
    expect($admin->fresh()->last_login_at)->not->toBeNull()
        ->and(session(AdminAuthenticator::PENDING_ID))->toBeNull()
        ->and(AdminAuditLog::query()->where('action', 'admin.login.succeeded')->where('admin_id', $admin->id)->exists())->toBeTrue();

    Notification::assertSentTo($admin, AdminSignedInNotification::class);
});

it('refuses the two-factor page without a correct password first', function () {
    $this->get(route('panel.two-factor.challenge'))->assertRedirect(route('panel.login'));
    $this->post(route('panel.two-factor.verify'), ['code' => '123456'])->assertRedirect(route('panel.login'));
});

it('rejects a wrong code', function () {
    $admin = panelAdmin();
    enterPassword($admin);

    $this->post(route('panel.two-factor.verify'), ['code' => '000000'])->assertSessionHasErrors('code');

    $this->assertGuest('admin');
    expect(AdminAuditLog::query()->where('action', 'admin.two_factor.failed')->count())->toBe(1);
});

it('rejects a code that was already used', function () {
    $admin = panelAdmin();
    $code = panelTotp($admin);

    enterPassword($admin);
    $this->post(route('panel.two-factor.verify'), ['code' => $code]);
    $this->post(route('panel.logout'));

    enterPassword($admin);
    $this->post(route('panel.two-factor.verify'), ['code' => $code])->assertSessionHasErrors('code');

    $this->assertGuest('admin');
});

it('accepts a recovery code once', function () {
    $admin = panelAdmin();

    enterPassword($admin);
    $this->post(route('panel.two-factor.verify'), ['code' => 'recovery-code-one'])->assertRedirect(route('panel.dashboard'));
    $this->assertAuthenticatedAs($admin, 'admin');
    expect($admin->fresh()->two_factor_recovery_codes)->toHaveCount(1)
        ->and(AdminAuditLog::query()->where('action', 'admin.recovery_code.used')->exists())->toBeTrue();

    $this->post(route('panel.logout'));

    enterPassword($admin);
    $this->post(route('panel.two-factor.verify'), ['code' => 'recovery-code-one'])->assertSessionHasErrors('code');
    $this->assertGuest('admin');
});

it('sends the browser back to the password after too many wrong codes', function () {
    config(['admin_panel.max_challenge_attempts' => 3]);
    $admin = panelAdmin();
    enterPassword($admin);

    $this->post(route('panel.two-factor.verify'), ['code' => '000000']);
    $this->post(route('panel.two-factor.verify'), ['code' => '000000']);
    $this->post(route('panel.two-factor.verify'), ['code' => '000000'])->assertRedirect(route('panel.login'));

    $this->post(route('panel.two-factor.verify'), ['code' => panelTotp($admin)])->assertRedirect(route('panel.login'));
    $this->assertGuest('admin');
});

it('expires the pending sign-in', function () {
    $admin = panelAdmin();
    enterPassword($admin);

    $this->travel(config('admin_panel.challenge_timeout') + 1)->minutes();

    $this->post(route('panel.two-factor.verify'), ['code' => panelTotp($admin)])->assertRedirect(route('panel.login'));
    $this->assertGuest('admin');
});

it('enrolls an admin without two-factor before signing them in', function () {
    $admin = Admin::factory()->create();
    enterPassword($admin);

    $this->get(route('panel.two-factor.setup'))->assertOk()->assertSee('Setup key');
    $this->assertGuest('admin');

    $admin->refresh();
    expect($admin->two_factor_secret)->not->toBeNull()
        ->and($admin->hasTwoFactorEnabled())->toBeFalse();

    $this->get(route('panel.two-factor.challenge'))->assertRedirect(route('panel.two-factor.setup'));
    $this->get(route('panel.dashboard'))->assertRedirect(route('panel.login'));

    $this->post(route('panel.two-factor.confirm'), ['code' => '000000'])->assertSessionHasErrors('code');

    $this->post(route('panel.two-factor.confirm'), ['code' => panelTotp($admin)])
        ->assertRedirect(route('panel.recovery-codes'));

    $this->assertAuthenticatedAs($admin, 'admin');
    expect($admin->fresh()->hasTwoFactorEnabled())->toBeTrue()
        ->and($admin->fresh()->two_factor_recovery_codes)->toHaveCount(8);

    $this->get(route('panel.recovery-codes'))->assertOk()->assertSee("won't be shown again", false);
    $this->get(route('panel.recovery-codes'))->assertRedirect(route('panel.account'));
});

it('keeps the same setup key across refreshes of the setup page', function () {
    $admin = Admin::factory()->create();
    enterPassword($admin);

    $this->get(route('panel.two-factor.setup'));
    $secret = $admin->fresh()->two_factor_secret;

    $this->get(route('panel.two-factor.setup'));

    expect($admin->fresh()->two_factor_secret)->toBe($secret);
});

it('stores the two-factor secret encrypted', function () {
    $admin = panelAdmin();

    $raw = DB::table('admins')->where('id', $admin->id)->value('two_factor_secret');

    expect($raw)->not->toBe($admin->two_factor_secret);
});
