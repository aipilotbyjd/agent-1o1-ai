<?php

use App\Http\Controllers\SuperAdmin\AccountController;
use Illuminate\Support\Facades\Route;

Route::prefix('account')->group(function () {
    Route::get('/', [AccountController::class, 'show'])->name('account');
    Route::put('password', [AccountController::class, 'updatePassword'])->name('account.password');
    Route::post('recovery-codes', [AccountController::class, 'regenerateRecoveryCodes'])->name('account.recovery-codes');
    Route::get('recovery-codes', [AccountController::class, 'showRecoveryCodes'])->name('recovery-codes');
});
