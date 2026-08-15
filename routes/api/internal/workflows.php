<?php

use App\Http\Controllers\Api\Internal\V1\Workflows\FolderController;
use App\Http\Controllers\Api\Internal\V1\Workflows\TagController;
use App\Http\Controllers\Api\Internal\V1\Workflows\WorkflowController;
use App\Http\Controllers\Api\Internal\V1\Workflows\WorkflowNodePinController;
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

        Route::put('{workflow}/tags', [TagController::class, 'syncForWorkflow'])->name('tags.sync');

        Route::post('{workflow}/nodes/{node}/pin', [WorkflowNodePinController::class, 'store'])->name('nodes.pin');
        Route::delete('{workflow}/nodes/{node}/pin', [WorkflowNodePinController::class, 'destroy'])->name('nodes.unpin');
    });

Route::middleware(['auth:api', 'workspace.context'])
    ->prefix('workspaces/{workspace}/folders')
    ->as('folders.')
    ->group(function () {
        Route::get('/', [FolderController::class, 'index'])->name('index');
        Route::post('/', [FolderController::class, 'store'])->name('store');
        Route::post('move-workflows', [FolderController::class, 'moveWorkflows'])->name('move-workflows');
        Route::patch('{folder}', [FolderController::class, 'update'])->name('update');
        Route::delete('{folder}', [FolderController::class, 'destroy'])->name('destroy');
    });

Route::middleware(['auth:api', 'workspace.context'])
    ->prefix('workspaces/{workspace}/tags')
    ->as('tags.')
    ->group(function () {
        Route::get('/', [TagController::class, 'index'])->name('index');
        Route::post('/', [TagController::class, 'store'])->name('store');
        Route::patch('{tag}', [TagController::class, 'update'])->name('update');
        Route::delete('{tag}', [TagController::class, 'destroy'])->name('destroy');
    });
