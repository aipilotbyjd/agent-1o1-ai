<?php

use App\Http\Controllers\Api\Internal\V1\Billing\PlanController;
use App\Http\Controllers\Api\Internal\V1\Billing\SubscriptionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:api', 'workspace.context'])
    ->prefix('workspaces/{workspace}/billing')
    ->as('billing.')
    ->group(function () {
        Route::get('plans', [PlanController::class, 'index'])->name('plans.index');
        Route::get('subscription', [SubscriptionController::class, 'show'])->name('subscription.show');
        Route::post('subscription/checkout', [SubscriptionController::class, 'checkout'])->name('subscription.checkout');
        Route::post('subscription/cancel', [SubscriptionController::class, 'cancel'])->name('subscription.cancel');
        Route::post('subscription/resume', [SubscriptionController::class, 'resume'])->name('subscription.resume');
    });
