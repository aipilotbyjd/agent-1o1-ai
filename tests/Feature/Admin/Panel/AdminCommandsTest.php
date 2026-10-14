<?php

use App\Models\Admin\Admin;
use App\Models\Admin\AdminAuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('creates an admin panel account in the admins table, not users', function () {
    $this->artisan('admin:create', ['email' => 'Ops@Example.com', '--name' => 'Ops'])
        ->expectsQuestion('Password (min 12 chars, upper & lower case, a number and a symbol)', 'Str0ng-Admin-Pass!')
        ->expectsQuestion('Confirm password', 'Str0ng-Admin-Pass!')
        ->assertSuccessful();

    $admin = Admin::query()->sole();

    expect($admin->email)->toBe('ops@example.com')
        ->and(Hash::check('Str0ng-Admin-Pass!', $admin->password))->toBeTrue()
        ->and($admin->hasTwoFactorEnabled())->toBeFalse()
        ->and(User::query()->where('email', 'ops@example.com')->exists())->toBeFalse()
        ->and(AdminAuditLog::query()->where('action', 'admin.created')->exists())->toBeTrue();
});

it('refuses a weak password, a mismatch or a duplicate email', function () {
    $this->artisan('admin:create', ['email' => 'ops@example.com', '--name' => 'Ops'])
        ->expectsQuestion('Password (min 12 chars, upper & lower case, a number and a symbol)', 'password')
        ->expectsQuestion('Confirm password', 'password')
        ->assertFailed();

    $this->artisan('admin:create', ['email' => 'ops@example.com', '--name' => 'Ops'])
        ->expectsQuestion('Password (min 12 chars, upper & lower case, a number and a symbol)', 'Str0ng-Admin-Pass!')
        ->expectsQuestion('Confirm password', 'Different-Pass-1!')
        ->assertFailed();

    Admin::factory()->create(['email' => 'taken@example.com']);

    $this->artisan('admin:create', ['email' => 'taken@example.com', '--name' => 'Ops'])
        ->expectsQuestion('Password (min 12 chars, upper & lower case, a number and a symbol)', 'Str0ng-Admin-Pass!')
        ->expectsQuestion('Confirm password', 'Str0ng-Admin-Pass!')
        ->assertFailed();

    expect(Admin::query()->count())->toBe(1);
});

it('resets an admin password', function () {
    $admin = panelAdmin();

    $this->artisan('admin:reset-password', ['email' => $admin->email])
        ->expectsQuestion('New password', 'Fresh-Admin-Pass1!')
        ->expectsQuestion('Confirm new password', 'Fresh-Admin-Pass1!')
        ->assertSuccessful();

    expect(Hash::check('Fresh-Admin-Pass1!', $admin->fresh()->password))->toBeTrue();

    $this->artisan('admin:reset-password', ['email' => 'nobody@example.com'])->assertFailed();
});

it('resets two-factor so it is enrolled again', function () {
    $admin = panelAdmin();

    $this->artisan('admin:reset-two-factor', ['email' => $admin->email])
        ->expectsConfirmation("Reset two-factor authentication for {$admin->email}?", 'yes')
        ->assertSuccessful();

    $admin->refresh();

    expect($admin->hasTwoFactorEnabled())->toBeFalse()
        ->and($admin->two_factor_secret)->toBeNull()
        ->and($admin->two_factor_recovery_codes)->toBeNull();
});

it('disables and re-enables an admin, with an audit trail', function () {
    $admin = panelAdmin();

    $this->artisan('admin:disable', ['email' => $admin->email])->assertSuccessful();
    expect($admin->fresh()->isDisabled())->toBeTrue();

    $this->artisan('admin:enable', ['email' => $admin->email])->assertSuccessful();
    expect($admin->fresh()->isDisabled())->toBeFalse()
        ->and(AdminAuditLog::query()->pluck('action')->all())->toBe(['admin.disabled', 'admin.enabled']);
});
