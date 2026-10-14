<?php

use App\Models\SuperAdmin\SuperAdmin;
use App\Models\SuperAdmin\SuperAdminAuditLog;
use App\Notifications\SuperAdmin\SuperAdminSignedInNotification;
use App\Services\SuperAdmin\SuperAdminAuthenticator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->withoutVite();
    Notification::fake();
});

function enterPassword(SuperAdmin $superAdmin): void
{
    test()->post(route('super-admin.login.store'), ['email' => $superAdmin->email, 'password' => 'password']);
}

it('signs in with a valid authenticator code', function () {
    $superAdmin = superAdmin();
    enterPassword($superAdmin);

    $this->post(route('super-admin.two-factor.verify'), ['code' => superAdminTotp($superAdmin)])
        ->assertRedirect(route('super-admin.dashboard'));

    $this->assertAuthenticatedAs($superAdmin, 'super_admin');
    expect($superAdmin->fresh()->last_login_at)->not->toBeNull()
        ->and(session(SuperAdminAuthenticator::PENDING_ID))->toBeNull()
        ->and(SuperAdminAuditLog::query()->where('action', 'super_admin.login.succeeded')->where('super_admin_id', $superAdmin->id)->exists())->toBeTrue();

    Notification::assertSentTo($superAdmin, SuperAdminSignedInNotification::class);
});

it('refuses the two-factor page without a correct password first', function () {
    $this->get(route('super-admin.two-factor.challenge'))->assertRedirect(route('super-admin.login'));
    $this->post(route('super-admin.two-factor.verify'), ['code' => '123456'])->assertRedirect(route('super-admin.login'));
});

it('rejects a wrong code', function () {
    $superAdmin = superAdmin();
    enterPassword($superAdmin);

    $this->post(route('super-admin.two-factor.verify'), ['code' => '000000'])->assertSessionHasErrors('code');

    $this->assertGuest('super_admin');
    expect(SuperAdminAuditLog::query()->where('action', 'super_admin.two_factor.failed')->count())->toBe(1);
});

it('rejects a code that was already used', function () {
    $superAdmin = superAdmin();
    $code = superAdminTotp($superAdmin);

    enterPassword($superAdmin);
    $this->post(route('super-admin.two-factor.verify'), ['code' => $code]);
    $this->post(route('super-admin.logout'));

    enterPassword($superAdmin);
    $this->post(route('super-admin.two-factor.verify'), ['code' => $code])->assertSessionHasErrors('code');

    $this->assertGuest('super_admin');
});

it('accepts a recovery code once', function () {
    $superAdmin = superAdmin();

    enterPassword($superAdmin);
    $this->post(route('super-admin.two-factor.verify'), ['code' => 'recovery-code-one'])->assertRedirect(route('super-admin.dashboard'));
    $this->assertAuthenticatedAs($superAdmin, 'super_admin');
    expect($superAdmin->fresh()->two_factor_recovery_codes)->toHaveCount(1)
        ->and(SuperAdminAuditLog::query()->where('action', 'super_admin.recovery_code.used')->exists())->toBeTrue();

    $this->post(route('super-admin.logout'));

    enterPassword($superAdmin);
    $this->post(route('super-admin.two-factor.verify'), ['code' => 'recovery-code-one'])->assertSessionHasErrors('code');
    $this->assertGuest('super_admin');
});

it('sends the browser back to the password after too many wrong codes', function () {
    config(['super_admin.max_challenge_attempts' => 3]);
    $superAdmin = superAdmin();
    enterPassword($superAdmin);

    $this->post(route('super-admin.two-factor.verify'), ['code' => '000000']);
    $this->post(route('super-admin.two-factor.verify'), ['code' => '000000']);
    $this->post(route('super-admin.two-factor.verify'), ['code' => '000000'])->assertRedirect(route('super-admin.login'));

    $this->post(route('super-admin.two-factor.verify'), ['code' => superAdminTotp($superAdmin)])->assertRedirect(route('super-admin.login'));
    $this->assertGuest('super_admin');
});

it('expires the pending sign-in', function () {
    $superAdmin = superAdmin();
    enterPassword($superAdmin);

    $this->travel(config('super_admin.challenge_timeout') + 1)->minutes();

    $this->post(route('super-admin.two-factor.verify'), ['code' => superAdminTotp($superAdmin)])->assertRedirect(route('super-admin.login'));
    $this->assertGuest('super_admin');
});

it('enrolls a super admin without two-factor before signing them in', function () {
    $superAdmin = SuperAdmin::factory()->create();
    enterPassword($superAdmin);

    $this->get(route('super-admin.two-factor.setup'))->assertOk()->assertSee('Setup key');
    $this->assertGuest('super_admin');

    $superAdmin->refresh();
    expect($superAdmin->two_factor_secret)->not->toBeNull()
        ->and($superAdmin->hasTwoFactorEnabled())->toBeFalse();

    $this->get(route('super-admin.two-factor.challenge'))->assertRedirect(route('super-admin.two-factor.setup'));
    $this->get(route('super-admin.dashboard'))->assertRedirect(route('super-admin.login'));

    $this->post(route('super-admin.two-factor.confirm'), ['code' => '000000'])->assertSessionHasErrors('code');

    $this->post(route('super-admin.two-factor.confirm'), ['code' => superAdminTotp($superAdmin)])
        ->assertRedirect(route('super-admin.recovery-codes'));

    $this->assertAuthenticatedAs($superAdmin, 'super_admin');
    expect($superAdmin->fresh()->hasTwoFactorEnabled())->toBeTrue()
        ->and($superAdmin->fresh()->two_factor_recovery_codes)->toHaveCount(8);

    $this->get(route('super-admin.recovery-codes'))->assertOk()->assertSee("won't be shown again", false);
    $this->get(route('super-admin.recovery-codes'))->assertRedirect(route('super-admin.account'));
});

it('keeps the same setup key across refreshes of the setup page', function () {
    $superAdmin = SuperAdmin::factory()->create();
    enterPassword($superAdmin);

    $this->get(route('super-admin.two-factor.setup'));
    $secret = $superAdmin->fresh()->two_factor_secret;

    $this->get(route('super-admin.two-factor.setup'));

    expect($superAdmin->fresh()->two_factor_secret)->toBe($secret);
});

it('stores the two-factor secret encrypted', function () {
    $superAdmin = superAdmin();

    $raw = DB::table('super_admins')->where('id', $superAdmin->id)->value('two_factor_secret');

    expect($raw)->not->toBe($superAdmin->two_factor_secret);
});
