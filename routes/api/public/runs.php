<?php

use App\Http\Controllers\Api\Public\V1\Runs\RunController;
use Illuminate\Support\Facades\Route;

Route::middleware('api-key:workflows:write')->post('workflows/{workflow}/runs', [RunController::class, 'store']);
Route::middleware('api-key:runs:read')->get('runs/{run}', [RunController::class, 'show']);
