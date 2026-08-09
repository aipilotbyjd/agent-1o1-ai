<?php

use App\Http\Controllers\Api\Internal\V1\Workflows\WorkflowBuilderController;
use App\Http\Controllers\Api\Internal\V1\Workflows\WorkflowBuilderMessageController;
use App\Http\Controllers\Api\Internal\V1\Workflows\WorkflowBuilderSessionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:api', 'workspace.context'])->group(function () {
    // Editor-only concerns on an already-created Workflow (node placement,
    // edges, autosave) — see WorkflowBuilderController.
    Route::prefix('workspaces/{workspace}/workflows')
        ->as('workflows.builder.')
        ->group(function () {
            Route::put('{workflow}/graph', [WorkflowBuilderController::class, 'replaceGraph'])->name('graph');
        });

    // The chat-based builder — sessions own a draft_graph edited through
    // WorkflowBuilderAgent's tools, promoted to a real Workflow when ready.
    Route::prefix('workspaces/{workspace}/workflow-builder-sessions')
        ->as('workflow-builder-sessions.')
        ->group(function () {
            Route::get('/', [WorkflowBuilderSessionController::class, 'index'])->name('index');
            Route::post('/', [WorkflowBuilderSessionController::class, 'store'])->name('store');
            Route::get('{session}', [WorkflowBuilderSessionController::class, 'show'])->name('show');
            Route::delete('{session}', [WorkflowBuilderSessionController::class, 'destroy'])->name('destroy');
            Route::post('{session}/promote', [WorkflowBuilderSessionController::class, 'promote'])->name('promote');

            Route::post('{session}/messages', [WorkflowBuilderMessageController::class, 'store'])->name('messages.store');
        });
});
