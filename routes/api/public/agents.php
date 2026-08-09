<?php

use App\Http\Controllers\Api\Public\V1\Agents\AgentSessionController;
use Illuminate\Support\Facades\Route;

Route::middleware('api-key:agents:invoke')->group(function () {
    Route::post('agents/{agent}/sessions', [AgentSessionController::class, 'store']);
    Route::get('agents/{agent}/sessions/{session}', [AgentSessionController::class, 'show']);
    Route::post('agents/{agent}/sessions/{session}/messages', [AgentSessionController::class, 'sendMessage']);
});
