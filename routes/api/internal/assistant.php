<?php

use App\Http\Controllers\Api\Internal\V1\AppConfigController;
use App\Http\Controllers\Api\Internal\V1\Assistant\AssistantAppController;
use App\Http\Controllers\Api\Internal\V1\Assistant\AssistantBriefingController;
use App\Http\Controllers\Api\Internal\V1\Assistant\AssistantController;
use App\Http\Controllers\Api\Internal\V1\Assistant\AssistantFeedbackController;
use App\Http\Controllers\Api\Internal\V1\Assistant\AssistantMeetingController;
use App\Http\Controllers\Api\Internal\V1\Assistant\AssistantMemoryController;
use App\Http\Controllers\Api\Internal\V1\Assistant\AssistantMessageController;
use App\Http\Controllers\Api\Internal\V1\Assistant\AssistantSessionController;
use App\Http\Controllers\Api\Internal\V1\Assistant\AssistantSituationController;
use App\Http\Controllers\Api\Internal\V1\Assistant\AssistantStyleController;
use App\Http\Controllers\Api\Internal\V1\Assistant\AssistantToolRuleController;
use App\Http\Controllers\Api\Internal\V1\Assistant\AssistantTranscriptionController;
use App\Http\Controllers\Api\Internal\V1\Assistant\AssistantTurnController;
use Illuminate\Support\Facades\Route;

// Public: the frontend reads the brand before anyone signs in.
Route::get('app-config', AppConfigController::class)
    ->middleware('throttle:60,1')
    ->name('app-config');

Route::middleware(['auth:api', 'workspace.context'])
    ->prefix('workspaces/{workspace}/assistant')
    ->as('assistant.')
    ->group(function () {
        Route::get('/', [AssistantController::class, 'show'])->name('show');
        Route::patch('/', [AssistantController::class, 'update'])->name('update');

        Route::get('sessions', [AssistantSessionController::class, 'index'])->name('sessions.index');
        Route::post('sessions', [AssistantSessionController::class, 'store'])->name('sessions.store');
        Route::get('sessions/{session}', [AssistantSessionController::class, 'show'])->name('sessions.show');
        Route::patch('sessions/{session}', [AssistantSessionController::class, 'update'])->name('sessions.update');
        Route::delete('sessions/{session}', [AssistantSessionController::class, 'destroy'])->name('sessions.destroy');
        Route::get('sessions/{session}/messages', [AssistantSessionController::class, 'messages'])->name('sessions.messages.index');
        Route::post('sessions/{session}/messages', [AssistantMessageController::class, 'store'])
            ->middleware('throttle:30,1')
            ->name('sessions.messages.store');

        Route::post('sessions/{session}/messages/{message}/feedback', [AssistantFeedbackController::class, 'store'])
            ->middleware('throttle:60,1')
            ->name('sessions.messages.feedback');

        Route::get('briefings/{type}', [AssistantBriefingController::class, 'show'])->whereIn('type', ['daily', 'meeting_prep'])->name('briefings.show');
        Route::put('briefings/{type}', [AssistantBriefingController::class, 'update'])->whereIn('type', ['daily', 'meeting_prep'])->name('briefings.update');
        Route::post('briefings/{type}/pause', [AssistantBriefingController::class, 'pause'])->whereIn('type', ['daily', 'meeting_prep'])->name('briefings.pause');
        Route::post('briefings/{type}/resume', [AssistantBriefingController::class, 'resume'])->whereIn('type', ['daily', 'meeting_prep'])->name('briefings.resume');
        Route::post('briefings/{type}/run-now', [AssistantBriefingController::class, 'runNow'])
            ->whereIn('type', ['daily', 'meeting_prep'])
            ->middleware('throttle:6,1')
            ->name('briefings.run-now');

        Route::get('meetings', [AssistantMeetingController::class, 'index'])->name('meetings.index');
        Route::post('meetings/{meeting}/prepare', [AssistantMeetingController::class, 'prepare'])
            ->middleware('throttle:10,1')
            ->name('meetings.prepare');
        Route::get('briefing-runs/{run}', [AssistantBriefingController::class, 'showRun'])->name('briefing-runs.show');

        Route::get('situations', [AssistantSituationController::class, 'index'])->name('situations.index');
        Route::patch('situations/{situation}', [AssistantSituationController::class, 'update'])->name('situations.update');
        Route::patch('situations/{situation}/steps/{step}', [AssistantSituationController::class, 'updateStep'])->name('situations.steps.update');
        Route::post('situations/{situation}/send', [AssistantSituationController::class, 'send'])->name('situations.send');

        Route::get('styles', [AssistantStyleController::class, 'index'])->name('styles.index');
        Route::put('styles/{kind}', [AssistantStyleController::class, 'update'])->name('styles.update');
        Route::post('styles/{kind}/revisions/{revision}/restore', [AssistantStyleController::class, 'restore'])->name('styles.restore');

        Route::get('sessions/{session}/context', [AssistantSessionController::class, 'context'])->name('sessions.context');

        Route::post('transcribe', AssistantTranscriptionController::class)
            ->middleware('throttle:20,1')
            ->name('transcribe');

        Route::get('sessions/{session}/turns/{turn}', [AssistantTurnController::class, 'show'])->name('sessions.turns.show');
        Route::post('sessions/{session}/turns/{turn}/cancel', [AssistantTurnController::class, 'cancel'])->name('sessions.turns.cancel');
        Route::post('sessions/{session}/turns/{turn}/decisions', [AssistantTurnController::class, 'decide'])->name('sessions.turns.decisions');

        Route::get('tool-rules', [AssistantToolRuleController::class, 'index'])->name('tool-rules.index');
        Route::put('tool-rules', [AssistantToolRuleController::class, 'update'])->name('tool-rules.update');

        Route::get('apps', [AssistantAppController::class, 'index'])->name('apps.index');

        Route::get('memories', [AssistantMemoryController::class, 'index'])->name('memories.index');
        Route::delete('memories/{memory}', [AssistantMemoryController::class, 'destroy'])->name('memories.destroy');
    });
