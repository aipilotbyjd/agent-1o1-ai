<?php

use App\Models\SuperAdmin\SuperAdminAuditLog;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->withoutVite();
    Notification::fake();
});

it('shows the account page', function () {
    $superAdmin = superAdmin(['name' => 'Ops Person']);
    signInAsSuperAdmin($superAdmin);

    $this->get(route('super-admin.account'))->assertOk()->assertSee('Ops Person')->assertSee('2 unused codes left');
});

it('changes the password and keeps the current session', function () {
    $superAdmin = superAdmin();
    signInAsSuperAdmin($superAdmin);

    $this->put(route('super-admin.account.password'), [
        'current_password' => 'password',
        'password' => 'N3w-SuperAdmin-Passw0rd!',
        'password_confirmation' => 'N3w-SuperAdmin-Passw0rd!',
    ])->assertRedirect(route('super-admin.account'))->assertSessionHasNoErrors();

    expect(Hash::check('N3w-SuperAdmin-Passw0rd!', $superAdmin->fresh()->password))->toBeTrue()
        ->and(SuperAdminAuditLog::query()->where('action', 'super_admin.password.changed')->exists())->toBeTrue();

    $this->get(route('super-admin.dashboard'))->assertOk();
});

it('refuses a password change without the current password or with a weak one', function () {
    $superAdmin = superAdmin();
    signInAsSuperAdmin($superAdmin);

    $this->put(route('super-admin.account.password'), [
        'current_password' => 'wrong-password',
        'password' => 'N3w-SuperAdmin-Passw0rd!',
        'password_confirmation' => 'N3w-SuperAdmin-Passw0rd!',
    ])->assertSessionHasErrors('current_password');

    $this->put(route('super-admin.account.password'), [
        'current_password' => 'password',
        'password' => 'short',
        'password_confirmation' => 'short',
    ])->assertSessionHasErrors('password');

    expect(Hash::check('password', $superAdmin->fresh()->password))->toBeTrue();
});

it('regenerates recovery codes behind the current password and shows them once', function () {
    $superAdmin = superAdmin();
    signInAsSuperAdmin($superAdmin);

    $this->post(route('super-admin.account.recovery-codes'), ['current_password' => 'wrong'])
        ->assertSessionHasErrorsIn('recoveryCodes', 'current_password');

    $this->post(route('super-admin.account.recovery-codes'), ['current_password' => 'password'])
        ->assertRedirect(route('super-admin.recovery-codes'));

    expect($superAdmin->fresh()->two_factor_recovery_codes)->toHaveCount(8);

    $this->get(route('super-admin.recovery-codes'))->assertOk();
    $this->get(route('super-admin.recovery-codes'))->assertRedirect(route('super-admin.account'));
});
