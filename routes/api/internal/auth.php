<?php

use App\Http\Controllers\Api\Internal\V1\Auth\ApiKeyController;
use App\Http\Controllers\Api\Internal\V1\Auth\AuthController;
use App\Http\Controllers\Api\Internal\V1\Auth\TwoFactorController;
use App\Http\Controllers\Api\Internal\V1\User\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->middleware('throttle:auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('refresh', [AuthController::class, 'refresh']);
    Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('reset-password', [AuthController::class, 'resetPassword']);

    Route::post('2fa/verify', [AuthController::class, 'verifyTwoFactor']);

    Route::get('social/{provider}/redirect', [AuthController::class, 'redirectToProvider'])
        ->where('provider', 'google|github');
    Route::get('social/{provider}/callback', [AuthController::class, 'handleProviderCallback'])
        ->where('provider', 'google|github');

    Route::get('verify-email/{id}/{hash}', [AuthController::class, 'verifyEmail'])
        ->middleware('signed')
        ->name('auth.verify-email');

    Route::middleware('auth:api')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('logout-all', [AuthController::class, 'logoutAll']);
        Route::post('change-password', [AuthController::class, 'changePassword']);
        Route::post('resend-verification', [AuthController::class, 'resendVerification']);

        Route::get('sessions', [AuthController::class, 'sessions']);
        Route::delete('sessions/{tokenId}', [AuthController::class, 'revokeSession']);

        Route::post('2fa/enable', [TwoFactorController::class, 'enable']);
        Route::post('2fa/confirm', [TwoFactorController::class, 'confirm']);
        Route::post('2fa/disable', [TwoFactorController::class, 'disable']);
        Route::get('2fa/recovery-codes', [TwoFactorController::class, 'recoveryCodes']);
        Route::post('2fa/recovery-codes/regenerate', [TwoFactorController::class, 'regenerateRecoveryCodes']);
    });
});

Route::middleware('auth:api')->group(function () {
    Route::get('user', [UserController::class, 'show']);
    Route::patch('user', [UserController::class, 'update']);
    Route::delete('user', [UserController::class, 'destroy']);

    Route::get('workspaces/{workspace}/api-keys', [ApiKeyController::class, 'index'])->middleware('workspace.context');
    Route::post('workspaces/{workspace}/api-keys', [ApiKeyController::class, 'store'])->middleware('workspace.context');
    Route::delete('workspaces/{workspace}/api-keys/{apiKey}', [ApiKeyController::class, 'destroy'])->middleware('workspace.context');
});
