<?php

use App\Models\SuperAdmin\SuperAdmin;
use PragmaRX\Google2FA\Google2FA;

/*
|--------------------------------------------------------------------------
| Super admin panel helpers
|--------------------------------------------------------------------------
|
| Loaded from tests/Pest.php. Kept in their own file so the super admin
| panel's test support stays apart from the rest of the suite's helpers.
|
*/

/**
 * A super admin account with two-factor already set up. Password: `password`.
 *
 * @param  array<string, mixed>  $attributes
 */
function superAdmin(array $attributes = []): SuperAdmin
{
    return SuperAdmin::factory()->withTwoFactor()->create($attributes);
}

/**
 * The authenticator code `$superAdmin`'s app would show right now.
 */
function superAdminTotp(SuperAdmin $superAdmin): string
{
    return app(Google2FA::class)->getCurrentOtp($superAdmin->fresh()->two_factor_secret);
}

/**
 * Signs in through the real password + two-factor flow.
 */
function signInAsSuperAdmin(SuperAdmin $superAdmin): void
{
    test()->post(route('super-admin.login.store'), ['email' => $superAdmin->email, 'password' => 'password']);
    test()->post(route('super-admin.two-factor.verify'), ['code' => superAdminTotp($superAdmin)]);
}
