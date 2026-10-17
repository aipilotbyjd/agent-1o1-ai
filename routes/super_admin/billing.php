<?php

use App\Http\Controllers\SuperAdmin\Billing\CreditTransactionController;
use App\Http\Controllers\SuperAdmin\Billing\PlanController;
use App\Http\Controllers\SuperAdmin\Billing\PlanGrantController;
use App\Http\Controllers\SuperAdmin\Billing\SubscriptionController;
use Illuminate\Support\Facades\Route;

Route::prefix('billing')->as('billing.')->group(function () {
    Route::get('subscriptions', [SubscriptionController::class, 'index'])->name('subscriptions.index');
    Route::get('credits', [CreditTransactionController::class, 'index'])->name('credits.index');
    Route::get('plan-grants', [PlanGrantController::class, 'index'])->name('plan-grants.index');
    Route::get('plans', PlanController::class)->name('plans.index');
});
