<?php

use App\Models\SuperAdmin\SuperAdminAuditLog;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Laravel\Passport\RefreshToken;
use Laravel\Passport\Token;

beforeEach(function () {
    $this->withoutVite();
    Notification::fake();
});

it('needs the super admin sign-in and password for every support action', function () {
    $user = User::factory()->create();

    $this->post(route('super-admin.users.sign-out-everywhere', $user))->assertRedirect(route('super-admin.login'));

    signInAsSuperAdmin(superAdmin());

    $this->post(route('super-admin.users.sign-out-everywhere', $user), ['current_password' => 'wrong'])
        ->assertSessionHasErrors('current_password', errorBag: 'support');
});

it('revokes a user\'s API tokens and logs it', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->token;
    $admin = superAdmin();
    signInAsSuperAdmin($admin);

    $this->post(route('super-admin.users.sign-out-everywhere', $user), ['current_password' => 'password'])
        ->assertRedirect(route('super-admin.users.show', $user));

    expect(Token::find($token->id)->revoked)->toBeTrue()
        ->and(RefreshToken::query()->where('revoked', false)->count())->toBe(0);

    $this->assertDatabaseHas('super_admin_audit_logs', [
        'super_admin_id' => $admin->id,
        'action' => 'user.signed_out_everywhere',
        'subject_id' => $user->id,
    ]);
});

it('resets a user\'s two-factor and logs it', function () {
    $user = User::factory()->create();
    $user->forceFill(['two_factor_secret' => 'JBSWY3DPEHPK3PXP', 'two_factor_confirmed_at' => now()])->save();
    signInAsSuperAdmin(superAdmin());

    $this->post(route('super-admin.users.reset-two-factor', $user), ['current_password' => 'password'])->assertRedirect();

    expect($user->fresh()->hasTwoFactorEnabled())->toBeFalse()
        ->and($user->fresh()->two_factor_secret)->toBeNull();
    expect(SuperAdminAuditLog::query()->where('action', 'user.two_factor.reset')->exists())->toBeTrue();
});

it('resends the verification email only to unverified users', function () {
    $unverified = User::factory()->unverified()->create();
    $verified = User::factory()->create();
    signInAsSuperAdmin(superAdmin());

    $this->post(route('super-admin.users.resend-verification', $unverified), ['current_password' => 'password']);
    Notification::assertSentTo($unverified, VerifyEmail::class);

    $this->post(route('super-admin.users.resend-verification', $verified), ['current_password' => 'password']);
    Notification::assertNotSentTo($verified, VerifyEmail::class);
});

it('lists the super admin team', function () {
    $admin = superAdmin(['email' => 'ops@acme.test']);
    signInAsSuperAdmin($admin);

    $this->get(route('super-admin.team.index'))->assertOk()->assertSee('ops@acme.test');
});

it('exports the audit log as CSV, neutralises formulas and logs the export', function () {
    $admin = superAdmin();
    signInAsSuperAdmin($admin);
    SuperAdminAuditLog::query()->create(['super_admin_id' => $admin->id, 'action' => '=cmd|calc']);

    $response = $this->get(route('super-admin.audit-log.export'));

    $response->assertOk()->assertDownload();
    $csv = $response->streamedContent();
    expect($csv)->toContain('created_at,super_admin,action')->toContain("'=cmd|calc");
    expect(SuperAdminAuditLog::query()->where('action', 'audit_log.exported')->exists())->toBeTrue();
});
