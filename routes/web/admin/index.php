<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin panel
|--------------------------------------------------------------------------
|
| Served on `admin_panel.domain` under `admin_panel.path` (default `/jd`), through the
| `admin` middleware group: IP allowlist, its own session cookie and strict
| security headers. Accounts live in `admins` and sign in through the
| `admin` guard — customer users and API tokens never get in.
|
*/

Route::domain(config('admin_panel.domain'))
    ->prefix(trim((string) config('admin_panel.path'), '/'))
    ->middleware('admin')
    ->as('panel.')
    ->group(function () {
        require __DIR__.'/auth.php';

        Route::middleware(['auth:admin', 'admin.session'])->group(function () {
            require __DIR__.'/dashboard.php';
            require __DIR__.'/account.php';
        });
    });
