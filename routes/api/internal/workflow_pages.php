<?php

use App\Http\Controllers\Api\Internal\V1\Workflows\WorkflowPageController;
use App\Http\Controllers\Api\Internal\V1\Workflows\WorkflowPageSubmissionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:api', 'workspace.context'])
    ->prefix('workspaces/{workspace}/pages')
    ->as('pages.')
    ->group(function () {
        Route::get('/', [WorkflowPageController::class, 'index'])->name('index');
        Route::post('/', [WorkflowPageController::class, 'store'])->name('store');
        Route::get('{page}', [WorkflowPageController::class, 'show'])->name('show');
        Route::patch('{page}', [WorkflowPageController::class, 'update'])->name('update');
        Route::delete('{page}', [WorkflowPageController::class, 'destroy'])->name('destroy');
        Route::post('{page}/duplicate', [WorkflowPageController::class, 'duplicate'])->name('duplicate');
        Route::post('{page}/publish', [WorkflowPageController::class, 'publish'])->name('publish');
        Route::post('{page}/unpublish', [WorkflowPageController::class, 'unpublish'])->name('unpublish');
        Route::post('{page}/rotate-link', [WorkflowPageController::class, 'rotateLink'])->name('rotate-link');

        // The page as a member using it sees it, and the runs started from it.
        Route::get('{page}/view', [WorkflowPageController::class, 'view'])->name('view');
        Route::get('{page}/submissions', [WorkflowPageSubmissionController::class, 'index'])->name('submissions.index');
        Route::post('{page}/submissions', [WorkflowPageSubmissionController::class, 'store'])->name('submissions.store');
        Route::get('{page}/submissions/{submission}', [WorkflowPageSubmissionController::class, 'show'])->name('submissions.show');
    });
