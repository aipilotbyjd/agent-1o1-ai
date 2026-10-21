<?php

use App\Models\SuperAdmin\SuperAdmin;
use App\Models\SuperAdmin\SuperAdminAuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('creates the super admin panel account in the super_admins table, not users', function () {
    $this->artisan('super-admin:create', ['email' => 'Ops@Example.com', '--name' => 'Ops'])
        ->expectsQuestion('Password (min 12 chars, upper & lower case, a number and a symbol)', 'Str0ng-SuperAdmin-Pass!')
        ->expectsQuestion('Confirm password', 'Str0ng-SuperAdmin-Pass!')
        ->assertSuccessful();

    $superAdmin = SuperAdmin::query()->sole();

    expect($superAdmin->email)->toBe('ops@example.com')
        ->and(Hash::check('Str0ng-SuperAdmin-Pass!', $superAdmin->password))->toBeTrue()
        ->and($superAdmin->hasTwoFactorEnabled())->toBeFalse()
        ->and(User::query()->where('email', 'ops@example.com')->exists())->toBeFalse()
        ->and(SuperAdminAuditLog::query()->where('action', 'super_admin.created')->exists())->toBeTrue();
});

it('refuses a weak password, a mismatch or a duplicate email', function () {
    $this->artisan('super-admin:create', ['email' => 'ops@example.com', '--name' => 'Ops'])
        ->expectsQuestion('Password (min 12 chars, upper & lower case, a number and a symbol)', 'password')
        ->expectsQuestion('Confirm password', 'password')
        ->assertFailed();

    $this->artisan('super-admin:create', ['email' => 'ops@example.com', '--name' => 'Ops'])
        ->expectsQuestion('Password (min 12 chars, upper & lower case, a number and a symbol)', 'Str0ng-SuperAdmin-Pass!')
        ->expectsQuestion('Confirm password', 'Different-Pass-1!')
        ->assertFailed();

    SuperAdmin::factory()->create(['email' => 'taken@example.com']);

    $this->artisan('super-admin:create', ['email' => 'taken@example.com', '--name' => 'Ops'])
        ->expectsQuestion('Password (min 12 chars, upper & lower case, a number and a symbol)', 'Str0ng-SuperAdmin-Pass!')
        ->expectsQuestion('Confirm password', 'Str0ng-SuperAdmin-Pass!')
        ->assertFailed();

    expect(SuperAdmin::query()->count())->toBe(1);
});

it('resets a super admin password', function () {
    $superAdmin = superAdmin();

    $this->artisan('super-admin:reset-password', ['email' => $superAdmin->email])
        ->expectsQuestion('New password', 'Fresh-SuperAdmin-Pass1!')
        ->expectsQuestion('Confirm new password', 'Fresh-SuperAdmin-Pass1!')
        ->assertSuccessful();

    expect(Hash::check('Fresh-SuperAdmin-Pass1!', $superAdmin->fresh()->password))->toBeTrue();

    $this->artisan('super-admin:reset-password', ['email' => 'nobody@example.com'])->assertFailed();
});

it('resets two-factor so it is enrolled again', function () {
    $superAdmin = superAdmin();

    $this->artisan('super-admin:reset-two-factor', ['email' => $superAdmin->email])
        ->expectsConfirmation("Reset two-factor authentication for {$superAdmin->email}?", 'yes')
        ->assertSuccessful();

    $superAdmin->refresh();

    expect($superAdmin->hasTwoFactorEnabled())->toBeFalse()
        ->and($superAdmin->two_factor_secret)->toBeNull()
        ->and($superAdmin->two_factor_recovery_codes)->toBeNull();
});

it('disables and re-enables a super admin, with an audit trail', function () {
    $superAdmin = superAdmin();

    $this->artisan('super-admin:disable', ['email' => $superAdmin->email])->assertSuccessful();
    expect($superAdmin->fresh()->isDisabled())->toBeTrue();

    $this->artisan('super-admin:enable', ['email' => $superAdmin->email])->assertSuccessful();
    expect($superAdmin->fresh()->isDisabled())->toBeFalse()
        ->and(SuperAdminAuditLog::query()->pluck('action')->all())->toBe(['super_admin.disabled', 'super_admin.enabled']);
});
