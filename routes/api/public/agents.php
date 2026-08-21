<?php

use App\Http\Controllers\Api\Public\V1\Agents\AgentController;
use App\Http\Controllers\Api\Public\V1\Agents\AgentSessionController;
use Illuminate\Support\Facades\Route;

Route::middleware('api-key:agents:invoke')->group(function () {
    // Discovery: a caller can't start a session without knowing an agent id.
    Route::get('agents', [AgentController::class, 'index']);
    Route::get('agents/{agent}', [AgentController::class, 'show']);

    Route::post('agents/{agent}/sessions', [AgentSessionController::class, 'store']);
    Route::get('agents/{agent}/sessions/{session}', [AgentSessionController::class, 'show']);
    Route::post('agents/{agent}/sessions/{session}/messages', [AgentSessionController::class, 'sendMessage']);
});
