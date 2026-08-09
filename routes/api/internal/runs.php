<?php

use App\Http\Controllers\Api\Internal\V1\Runs\RunController;
use App\Http\Controllers\Api\Internal\V1\Runs\WorkflowApprovalController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:api', 'workspace.context'])
    ->prefix('workspaces/{workspace}')
    ->as('runs.')
    ->group(function () {
        Route::post('workflows/{workflow}/runs', [RunController::class, 'store'])->name('store');
        Route::get('runs', [RunController::class, 'index'])->name('index');
        Route::get('runs/{run}', [RunController::class, 'show'])->name('show');
        Route::post('runs/{run}/approvals/{approval}/decide', [WorkflowApprovalController::class, 'decide'])->name('approvals.decide');
    });
