<?php

use App\Http\Controllers\Api\Internal\V1\Templates\AgentTemplateController;
use App\Http\Controllers\Api\Internal\V1\Templates\TemplateCollectionController;
use App\Http\Controllers\Api\Internal\V1\Templates\TemplateCollectionItemController;
use App\Http\Controllers\Api\Internal\V1\Templates\WorkflowTemplateController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:api', 'workspace.context'])
    ->prefix('workspaces/{workspace}')
    ->group(function () {
        Route::prefix('workflow-templates')->as('workflow-templates.')->group(function () {
            Route::get('/', [WorkflowTemplateController::class, 'index'])->name('index');
            Route::post('/', [WorkflowTemplateController::class, 'store'])->name('store');
            Route::get('{workflowTemplate}', [WorkflowTemplateController::class, 'show'])->name('show');
            Route::patch('{workflowTemplate}', [WorkflowTemplateController::class, 'update'])->name('update');
            Route::delete('{workflowTemplate}', [WorkflowTemplateController::class, 'destroy'])->name('destroy');
            Route::post('{workflowTemplate}/use', [WorkflowTemplateController::class, 'use'])->name('use');
        });

        Route::prefix('agent-templates')->as('agent-templates.')->group(function () {
            Route::get('/', [AgentTemplateController::class, 'index'])->name('index');
            Route::post('/', [AgentTemplateController::class, 'store'])->name('store');
            Route::get('{agentTemplate}', [AgentTemplateController::class, 'show'])->name('show');
            Route::patch('{agentTemplate}', [AgentTemplateController::class, 'update'])->name('update');
            Route::delete('{agentTemplate}', [AgentTemplateController::class, 'destroy'])->name('destroy');
            Route::post('{agentTemplate}/use', [AgentTemplateController::class, 'use'])->name('use');
        });

        Route::prefix('template-collections')->as('template-collections.')->group(function () {
            Route::get('/', [TemplateCollectionController::class, 'index'])->name('index');
            Route::post('/', [TemplateCollectionController::class, 'store'])->name('store');
            Route::get('{templateCollection}', [TemplateCollectionController::class, 'show'])->name('show');
            Route::patch('{templateCollection}', [TemplateCollectionController::class, 'update'])->name('update');
            Route::delete('{templateCollection}', [TemplateCollectionController::class, 'destroy'])->name('destroy');
            Route::post('{templateCollection}/use', [TemplateCollectionController::class, 'use'])->name('use');

            Route::post('{templateCollection}/items', [TemplateCollectionItemController::class, 'store'])->name('items.store');
            Route::patch('{templateCollection}/items/reorder', [TemplateCollectionItemController::class, 'reorder'])->name('items.reorder');
            Route::delete('{templateCollection}/items/{item}', [TemplateCollectionItemController::class, 'destroy'])->name('items.destroy');
        });

        Route::post('workflows/{workflow}/save-as-template', [WorkflowTemplateController::class, 'storeFromWorkflow'])->name('workflows.save-as-template');
        Route::post('agents/{agent}/save-as-template', [AgentTemplateController::class, 'storeFromAgent'])->name('agents.save-as-template');
    });
