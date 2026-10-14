<?php

use App\Http\Controllers\Admin\Auth\LoginController;
use App\Http\Controllers\Admin\Auth\TwoFactorChallengeController;
use App\Http\Controllers\Admin\Auth\TwoFactorSetupController;
use Illuminate\Support\Facades\Route;

/*
| Password first, then the second factor (or first-time enrollment). The
| two-factor pages need the pending state a correct password opens.
*/
Route::middleware('guest:admin')->group(function () {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->middleware('throttle:admin-login')->name('login.store');

    Route::middleware('admin.pending')->prefix('two-factor')->as('two-factor.')->group(function () {
        Route::get('/', [TwoFactorChallengeController::class, 'create'])->name('challenge');
        Route::post('/', [TwoFactorChallengeController::class, 'store'])->middleware('throttle:admin-two-factor')->name('verify');
        Route::get('setup', [TwoFactorSetupController::class, 'create'])->name('setup');
        Route::post('setup', [TwoFactorSetupController::class, 'store'])->middleware('throttle:admin-two-factor')->name('confirm');
    });
});

Route::post('logout', [LoginController::class, 'destroy'])->middleware('auth:admin')->name('logout');
