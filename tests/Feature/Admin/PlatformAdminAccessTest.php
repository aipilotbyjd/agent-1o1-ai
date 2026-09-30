<?php

use App\Models\Admin\AdminAuditLog;
use App\Models\User;
use Laravel\Passport\Passport;

it('refuses the admin API to ordinary users', function () {
    Passport::actingAs(User::factory()->create());

    $this->getJson('/api/v1/admin/referrals/programs')->assertForbidden();
});

it('refuses the admin API to an admin without two-factor authentication', function () {
    $admin = User::factory()->create();
    $admin->forceFill(['is_platform_admin' => true])->save();

    Passport::actingAs($admin);

    $this->getJson('/api/v1/admin/referrals/programs')
        ->assertForbidden()
        ->assertJsonPath('message', 'Enable two-factor authentication to use the admin API.');
});

it('lets a platform admin with two-factor authentication in', function () {
    Passport::actingAs(platformAdmin());

    $this->getJson('/api/v1/admin/referrals/programs')->assertSuccessful();
});

it('cannot be made a platform admin through the profile API', function () {
    Passport::actingAs($user = User::factory()->create());

    $this->patchJson('/api/v1/user', ['name' => 'Me', 'is_platform_admin' => true]);

    expect($user->fresh()->isPlatformAdmin())->toBeFalse();
});

it('grants and revokes platform admin from the command line, with an audit trail', function () {
    $user = User::factory()->create(['email' => 'ops@acme.test']);

    $this->artisan('admin:grant', ['email' => 'ops@acme.test'])->assertSuccessful();
    expect($user->fresh()->isPlatformAdmin())->toBeTrue();

    $this->artisan('admin:revoke', ['email' => 'ops@acme.test'])->assertSuccessful();
    expect($user->fresh()->isPlatformAdmin())->toBeFalse()
        ->and(AdminAuditLog::query()->pluck('action')->all())->toBe(['platform_admin.granted', 'platform_admin.revoked']);

    $this->artisan('admin:grant', ['email' => 'nobody@acme.test'])->assertFailed();
});

it('exposes the admin flag on the current user', function () {
    Passport::actingAs(platformAdmin());

    $this->getJson('/api/v1/user')->assertSuccessful()->assertJsonPath('data.user.is_platform_admin', true);
});

it('hides the admin API entirely when it is switched off', function () {
    config(['platform_admin.api_enabled' => false]);
    Passport::actingAs(platformAdmin());

    $this->getJson('/api/v1/admin/referrals/programs')->assertNotFound();
    $this->getJson('/api/v1/admin/audit-log')->assertNotFound();
});
