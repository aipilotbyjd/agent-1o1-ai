<?php

use App\Http\Controllers\Api\Internal\V1\Agents\AgentController;
use App\Http\Controllers\Api\Internal\V1\Agents\AgentEvalCaseController;
use App\Http\Controllers\Api\Internal\V1\Agents\AgentEvalRunController;
use App\Http\Controllers\Api\Internal\V1\Agents\AgentEvalSuiteController;
use App\Http\Controllers\Api\Internal\V1\Agents\AgentEvaluationSettingsController;
use App\Http\Controllers\Api\Internal\V1\Agents\AgentKnowledgeController;
use App\Http\Controllers\Api\Internal\V1\Agents\AgentMemoryController;
use App\Http\Controllers\Api\Internal\V1\Agents\AgentSessionController;
use App\Http\Controllers\Api\Internal\V1\Agents\AgentSessionEvaluationController;
use App\Http\Controllers\Api\Internal\V1\Agents\AgentSessionStreamController;
use App\Http\Controllers\Api\Internal\V1\Agents\AgentSkillController;
use App\Http\Controllers\Api\Internal\V1\Agents\AgentToolBindingController;
use App\Http\Controllers\Api\Internal\V1\Agents\AgentVersionController;
use App\Http\Controllers\Api\Internal\V1\Agents\AgentWorkflowToolController;
use App\Http\Controllers\Api\Internal\V1\Agents\ReflectionController;
use App\Http\Controllers\Api\Internal\V1\Agents\ReflectionRunController;
use App\Http\Controllers\Api\Internal\V1\Agents\ReflectionSettingsController;
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
        Route::post('{agent}/duplicate', [AgentController::class, 'duplicate'])->name('duplicate');

        // Behavioral history. Addressed by version number, not row id —
        // see AgentVersionController.
        Route::get('{agent}/versions', [AgentVersionController::class, 'index'])->name('versions.index');
        Route::get('{agent}/versions/{version}', [AgentVersionController::class, 'show'])->name('versions.show');
        Route::post('{agent}/versions/{version}/restore', [AgentVersionController::class, 'restore'])->name('versions.restore');

        Route::get('{agent}/sessions', [AgentSessionController::class, 'index'])->name('sessions.index');
        Route::post('{agent}/sessions', [AgentSessionController::class, 'store'])->name('sessions.store');
        Route::get('{agent}/sessions/{session}', [AgentSessionController::class, 'show'])->name('sessions.show');
        Route::patch('{agent}/sessions/{session}', [AgentSessionController::class, 'update'])->name('sessions.update');
        Route::delete('{agent}/sessions/{session}', [AgentSessionController::class, 'destroy'])->name('sessions.destroy');
        Route::get('{agent}/sessions/{session}/messages', [AgentSessionController::class, 'messages'])->name('sessions.messages.index');
        Route::post('{agent}/sessions/{session}/messages', [AgentSessionController::class, 'sendMessage'])->name('sessions.messages.store');

        // The same turn as above, delivered as server-sent events — see
        // AgentSessionStreamController for the event names.
        Route::post('{agent}/sessions/{session}/messages/stream', [AgentSessionStreamController::class, 'store'])->name('sessions.messages.stream');

        Route::get('{agent}/tool-bindings', [AgentToolBindingController::class, 'index'])->name('tool-bindings.index');
        Route::post('{agent}/tool-bindings', [AgentToolBindingController::class, 'store'])->name('tool-bindings.store');
        Route::delete('{agent}/tool-bindings/{toolBinding}', [AgentToolBindingController::class, 'destroy'])->name('tool-bindings.destroy');

        Route::get('{agent}/workflows', [AgentWorkflowToolController::class, 'index'])->name('workflows.index');
        Route::post('{agent}/workflows/{workflow}', [AgentWorkflowToolController::class, 'store'])->name('workflows.store');
        Route::delete('{agent}/workflows/{workflow}', [AgentWorkflowToolController::class, 'destroy'])->name('workflows.destroy');

        Route::get('{agent}/skills', [AgentSkillController::class, 'index'])->name('skills.index');
        Route::post('{agent}/skills/{skill}', [AgentSkillController::class, 'store'])->name('skills.store');
        Route::delete('{agent}/skills/{skill}', [AgentSkillController::class, 'destroy'])->name('skills.destroy');

        Route::get('{agent}/knowledge', [AgentKnowledgeController::class, 'index'])->name('knowledge.index');
        Route::post('{agent}/knowledge', [AgentKnowledgeController::class, 'store'])->name('knowledge.store');
        Route::patch('{agent}/knowledge/{knowledge}', [AgentKnowledgeController::class, 'update'])->name('knowledge.update');
        Route::delete('{agent}/knowledge/{knowledge}', [AgentKnowledgeController::class, 'destroy'])->name('knowledge.destroy');

        // Evals: saved suites of cases graded against the agent — see
        // Services\Agents\EvalRunner.
        Route::get('{agent}/eval-suites', [AgentEvalSuiteController::class, 'index'])->name('eval-suites.index');
        Route::post('{agent}/eval-suites', [AgentEvalSuiteController::class, 'store'])->name('eval-suites.store');
        Route::get('{agent}/eval-suites/{suite}', [AgentEvalSuiteController::class, 'show'])->name('eval-suites.show');
        Route::patch('{agent}/eval-suites/{suite}', [AgentEvalSuiteController::class, 'update'])->name('eval-suites.update');
        Route::delete('{agent}/eval-suites/{suite}', [AgentEvalSuiteController::class, 'destroy'])->name('eval-suites.destroy');

        Route::get('{agent}/eval-suites/{suite}/cases', [AgentEvalCaseController::class, 'index'])->name('eval-suites.cases.index');
        Route::post('{agent}/eval-suites/{suite}/cases', [AgentEvalCaseController::class, 'store'])->name('eval-suites.cases.store');
        Route::patch('{agent}/eval-suites/{suite}/cases/{case}', [AgentEvalCaseController::class, 'update'])->name('eval-suites.cases.update');
        Route::delete('{agent}/eval-suites/{suite}/cases/{case}', [AgentEvalCaseController::class, 'destroy'])->name('eval-suites.cases.destroy');

        Route::get('{agent}/eval-suites/{suite}/runs', [AgentEvalRunController::class, 'index'])->name('eval-suites.runs.index');
        Route::post('{agent}/eval-suites/{suite}/runs', [AgentEvalRunController::class, 'store'])->name('eval-suites.runs.store');
        Route::get('{agent}/eval-suites/{suite}/runs/{evalRun}', [AgentEvalRunController::class, 'show'])->name('eval-suites.runs.show');

        Route::get('{agent}/memories', [AgentMemoryController::class, 'index'])->name('memories.index');
        Route::post('{agent}/memories', [AgentMemoryController::class, 'store'])->name('memories.store');
        Route::patch('{agent}/memories/{memory}', [AgentMemoryController::class, 'update'])->name('memories.update');
        Route::delete('{agent}/memories/{memory}', [AgentMemoryController::class, 'destroy'])->name('memories.destroy');

        // Reflections: periodic review of the agent's own past sessions —
        // see Services\Agents\ReflectionAnalyzer.
        Route::get('{agent}/reflection-settings', [ReflectionSettingsController::class, 'show'])->name('reflection-settings.show');
        Route::patch('{agent}/reflection-settings', [ReflectionSettingsController::class, 'update'])->name('reflection-settings.update');

        Route::get('{agent}/reflection-runs', [ReflectionRunController::class, 'index'])->name('reflection-runs.index');
        Route::post('{agent}/reflection-runs', [ReflectionRunController::class, 'store'])->name('reflection-runs.store');
        Route::get('{agent}/reflection-runs/{run}', [ReflectionRunController::class, 'show'])->name('reflection-runs.show');

        Route::get('{agent}/reflections', [ReflectionController::class, 'index'])->name('reflections.index');
        Route::get('{agent}/reflections/{reflection}', [ReflectionController::class, 'show'])->name('reflections.show');
        Route::post('{agent}/reflections/{reflection}/apply', [ReflectionController::class, 'apply'])->name('reflections.apply');
        Route::post('{agent}/reflections/{reflection}/dismiss', [ReflectionController::class, 'dismiss'])->name('reflections.dismiss');

        // Evaluations: automatic QA grading of live sessions — see
        // Services\Agents\SessionEvaluator.
        Route::get('{agent}/evaluation-settings', [AgentEvaluationSettingsController::class, 'show'])->name('evaluation-settings.show');
        Route::patch('{agent}/evaluation-settings', [AgentEvaluationSettingsController::class, 'update'])->name('evaluation-settings.update');

        Route::get('{agent}/session-evaluations', [AgentSessionEvaluationController::class, 'index'])->name('session-evaluations.index');
        Route::get('{agent}/session-evaluations/{evaluation}', [AgentSessionEvaluationController::class, 'show'])->name('session-evaluations.show');
        Route::post('{agent}/sessions/{session}/evaluation', [AgentSessionEvaluationController::class, 'run'])->name('sessions.evaluation.run');
    });
