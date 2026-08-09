<?php

use App\Http\Controllers\Api\Internal\V1\Workflows\WorkflowController;
use App\Http\Controllers\Api\Internal\V1\Workflows\WorkflowVersionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:api', 'workspace.context'])
    ->prefix('workspaces/{workspace}/workflows')
    ->as('workflows.')
    ->group(function () {
        Route::get('/', [WorkflowController::class, 'index'])->name('index');
        Route::post('/', [WorkflowController::class, 'store'])->name('store');
        Route::get('{workflow}', [WorkflowController::class, 'show'])->name('show');
        Route::patch('{workflow}', [WorkflowController::class, 'update'])->name('update');
        Route::delete('{workflow}', [WorkflowController::class, 'destroy'])->name('destroy');

        Route::get('{workflow}/versions', [WorkflowVersionController::class, 'index'])->name('versions.index');
        Route::post('{workflow}/versions', [WorkflowVersionController::class, 'store'])->name('versions.store');
        Route::get('{workflow}/versions/{version}', [WorkflowVersionController::class, 'show'])->name('versions.show');
    });
