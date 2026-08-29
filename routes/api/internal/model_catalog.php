<?php

use App\Http\Controllers\Api\Internal\V1\Ai\ModelCatalogController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:api')->group(function () {
    Route::get('model-catalog', [ModelCatalogController::class, 'index'])->name('model-catalog.index');
});
