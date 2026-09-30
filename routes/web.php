<?php

use App\Http\Controllers\AgentActionSignedDecisionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Linked from approval emails; the signature is the authentication — see
// AgentActionSignedDecisionController.
Route::middleware('signed')->group(function () {
    Route::get('agent-actions/{action}/decision', [AgentActionSignedDecisionController::class, 'show'])->name('agent-actions.signed-decision.show');
    Route::post('agent-actions/{action}/decision', [AgentActionSignedDecisionController::class, 'store'])->name('agent-actions.signed-decision.store');
});
