<?php

use App\Http\Controllers\Api\Internal\V1\Billing\BillingController;
use App\Http\Controllers\Api\Internal\V1\Billing\CreditController;
use App\Http\Controllers\Api\Internal\V1\Billing\CreditPackController;
use App\Http\Controllers\Api\Internal\V1\Billing\PlanController;
use App\Http\Controllers\Api\Internal\V1\Billing\SubscriptionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:api', 'workspace.context'])
    ->prefix('workspaces/{workspace}/billing')
    ->as('billing.')
    ->group(function () {
        Route::get('/', [BillingController::class, 'overview'])->name('overview');
        Route::get('plans', [PlanController::class, 'index'])->name('plans.index');
        Route::get('subscription', [SubscriptionController::class, 'show'])->name('subscription.show');
        Route::post('subscription/checkout', [SubscriptionController::class, 'checkout'])->name('subscription.checkout');
        Route::post('subscription/cancel', [SubscriptionController::class, 'cancel'])->name('subscription.cancel');
        Route::post('subscription/resume', [SubscriptionController::class, 'resume'])->name('subscription.resume');

        Route::get('credit-packs', [CreditPackController::class, 'index'])->name('credit-packs.index');
        Route::get('credit-packs/purchased', [CreditPackController::class, 'purchased'])->name('credit-packs.purchased');
        Route::post('credit-packs/checkout', [CreditPackController::class, 'checkout'])->name('credit-packs.checkout');

        Route::get('credits', [CreditController::class, 'index'])->name('credits.index');
    });
