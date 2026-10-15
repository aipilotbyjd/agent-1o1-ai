<?php

use App\Http\Controllers\SuperAdmin\UserController;
use App\Http\Controllers\SuperAdmin\UserSupportController;
use Illuminate\Support\Facades\Route;

Route::get('users', [UserController::class, 'index'])->name('users.index');
Route::get('users/{user}', [UserController::class, 'show'])->whereUuid('user')->name('users.show');

Route::prefix('users/{user}')->whereUuid('user')->as('users.')->group(function () {
    Route::post('sign-out-everywhere', [UserSupportController::class, 'signOutEverywhere'])->name('sign-out-everywhere');
    Route::post('reset-two-factor', [UserSupportController::class, 'resetTwoFactor'])->name('reset-two-factor');
    Route::post('resend-verification', [UserSupportController::class, 'resendVerification'])->name('resend-verification');
    Route::post('suspend', [UserSupportController::class, 'suspend'])->name('suspend');
    Route::post('unsuspend', [UserSupportController::class, 'unsuspend'])->name('unsuspend');
});
