<?php

use App\Models\Admin\AdminAuditLog;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->withoutVite();
    Notification::fake();
});

it('shows the account page', function () {
    $admin = panelAdmin(['name' => 'Ops Person']);
    signInToPanel($admin);

    $this->get(route('panel.account'))->assertOk()->assertSee('Ops Person')->assertSee('2 unused codes left');
});

it('changes the password and keeps the current session', function () {
    $admin = panelAdmin();
    signInToPanel($admin);

    $this->put(route('panel.account.password'), [
        'current_password' => 'password',
        'password' => 'N3w-Admin-Passw0rd!',
        'password_confirmation' => 'N3w-Admin-Passw0rd!',
    ])->assertRedirect(route('panel.account'))->assertSessionHasNoErrors();

    expect(Hash::check('N3w-Admin-Passw0rd!', $admin->fresh()->password))->toBeTrue()
        ->and(AdminAuditLog::query()->where('action', 'admin.password.changed')->exists())->toBeTrue();

    $this->get(route('panel.dashboard'))->assertOk();
});

it('refuses a password change without the current password or with a weak one', function () {
    $admin = panelAdmin();
    signInToPanel($admin);

    $this->put(route('panel.account.password'), [
        'current_password' => 'wrong-password',
        'password' => 'N3w-Admin-Passw0rd!',
        'password_confirmation' => 'N3w-Admin-Passw0rd!',
    ])->assertSessionHasErrors('current_password');

    $this->put(route('panel.account.password'), [
        'current_password' => 'password',
        'password' => 'short',
        'password_confirmation' => 'short',
    ])->assertSessionHasErrors('password');

    expect(Hash::check('password', $admin->fresh()->password))->toBeTrue();
});

it('regenerates recovery codes behind the current password and shows them once', function () {
    $admin = panelAdmin();
    signInToPanel($admin);

    $this->post(route('panel.account.recovery-codes'), ['current_password' => 'wrong'])
        ->assertSessionHasErrorsIn('recoveryCodes', 'current_password');

    $this->post(route('panel.account.recovery-codes'), ['current_password' => 'password'])
        ->assertRedirect(route('panel.recovery-codes'));

    expect($admin->fresh()->two_factor_recovery_codes)->toHaveCount(8);

    $this->get(route('panel.recovery-codes'))->assertOk();
    $this->get(route('panel.recovery-codes'))->assertRedirect(route('panel.account'));
});
