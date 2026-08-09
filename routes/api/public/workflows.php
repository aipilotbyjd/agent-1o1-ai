<?php

use App\Http\Controllers\Api\Public\V1\Workflows\WorkflowController;
use Illuminate\Support\Facades\Route;

Route::middleware('api-key:workflows:read')->group(function () {
    Route::get('workflows', [WorkflowController::class, 'index']);
    Route::get('workflows/{workflow}', [WorkflowController::class, 'show']);
});
