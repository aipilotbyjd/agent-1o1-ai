<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Super admin panel
|--------------------------------------------------------------------------
|
| Served on `super_admin.domain` under `super_admin.path` (default `/jd`), through the
| `super-admin` middleware group: IP allowlist, its own session cookie and strict
| security headers. Accounts live in `super_admins` and sign in through the
| `super_admin` guard — customer users and API tokens never get in.
|
*/

Route::domain(config('super_admin.domain'))
    ->prefix(trim((string) config('super_admin.path'), '/'))
    ->middleware('super-admin')
    ->as('super-admin.')
    ->group(function () {
        require __DIR__.'/auth.php';

        Route::middleware(['auth:super_admin', 'super-admin.session'])->group(function () {
            require __DIR__.'/dashboard.php';
            require __DIR__.'/users.php';
            require __DIR__.'/workspaces.php';
            require __DIR__.'/runs.php';
            require __DIR__.'/billing.php';
            require __DIR__.'/system.php';
            require __DIR__.'/audit_log.php';
            require __DIR__.'/team.php';
            require __DIR__.'/account.php';
        });
    });
