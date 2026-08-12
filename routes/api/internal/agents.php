<?php

use App\Http\Controllers\Api\Internal\V1\Agents\AgentController;
use App\Http\Controllers\Api\Internal\V1\Agents\AgentMemoryController;
use App\Http\Controllers\Api\Internal\V1\Agents\AgentSessionController;
use App\Http\Controllers\Api\Internal\V1\Agents\AgentSkillController;
use App\Http\Controllers\Api\Internal\V1\Agents\AgentToolBindingController;
use App\Http\Controllers\Api\Internal\V1\Agents\AgentWorkflowToolController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:api', 'workspace.context'])
    ->prefix('workspaces/{workspace}/agents')
    ->as('agents.')
    ->group(function () {
        Route::get('/', [AgentController::class, 'index'])->name('index');
        Route::post('/', [AgentController::class, 'store'])->name('store');
        Route::get('{agent}', [AgentController::class, 'show'])->name('show');
        Route::patch('{agent}', [AgentController::class, 'update'])->name('update');
        Route::delete('{agent}', [AgentController::class, 'destroy'])->name('destroy');

        Route::get('{agent}/sessions', [AgentSessionController::class, 'index'])->name('sessions.index');
        Route::post('{agent}/sessions', [AgentSessionController::class, 'store'])->name('sessions.store');
        Route::get('{agent}/sessions/{session}', [AgentSessionController::class, 'show'])->name('sessions.show');
        Route::post('{agent}/sessions/{session}/messages', [AgentSessionController::class, 'sendMessage'])->name('sessions.messages.store');

        Route::get('{agent}/tool-bindings', [AgentToolBindingController::class, 'index'])->name('tool-bindings.index');
        Route::post('{agent}/tool-bindings', [AgentToolBindingController::class, 'store'])->name('tool-bindings.store');
        Route::delete('{agent}/tool-bindings/{toolBinding}', [AgentToolBindingController::class, 'destroy'])->name('tool-bindings.destroy');

        Route::get('{agent}/workflows', [AgentWorkflowToolController::class, 'index'])->name('workflows.index');
        Route::post('{agent}/workflows/{workflow}', [AgentWorkflowToolController::class, 'store'])->name('workflows.store');
        Route::delete('{agent}/workflows/{workflow}', [AgentWorkflowToolController::class, 'destroy'])->name('workflows.destroy');

        Route::get('{agent}/skills', [AgentSkillController::class, 'index'])->name('skills.index');
        Route::post('{agent}/skills/{skill}', [AgentSkillController::class, 'store'])->name('skills.store');
        Route::delete('{agent}/skills/{skill}', [AgentSkillController::class, 'destroy'])->name('skills.destroy');

        Route::get('{agent}/memories', [AgentMemoryController::class, 'index'])->name('memories.index');
        Route::post('{agent}/memories', [AgentMemoryController::class, 'store'])->name('memories.store');
        Route::patch('{agent}/memories/{memory}', [AgentMemoryController::class, 'update'])->name('memories.update');
        Route::delete('{agent}/memories/{memory}', [AgentMemoryController::class, 'destroy'])->name('memories.destroy');
    });
