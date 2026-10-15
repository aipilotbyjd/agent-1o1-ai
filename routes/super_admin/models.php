<?php

use App\Http\Controllers\SuperAdmin\Models\ModelCatalogController;
use App\Http\Controllers\SuperAdmin\Models\ModelRouteController;
use Illuminate\Support\Facades\Route;

Route::prefix('models')->as('models.')->group(function () {
    Route::get('/', [ModelCatalogController::class, 'index'])->name('index');
    Route::get('create', [ModelCatalogController::class, 'create'])->name('create');
    Route::post('/', [ModelCatalogController::class, 'store'])->name('store');

    Route::prefix('{model}')->whereUuid('model')->group(function () {
        Route::get('/', [ModelCatalogController::class, 'show'])->name('show');
        Route::get('edit', [ModelCatalogController::class, 'edit'])->name('edit');
        Route::put('/', [ModelCatalogController::class, 'update'])->name('update');

        Route::post('routes', [ModelRouteController::class, 'store'])->name('routes.store');
        Route::put('routes/{route}', [ModelRouteController::class, 'update'])->whereUuid('route')->name('routes.update');
        Route::post('routes/{route}/reset-failures', [ModelRouteController::class, 'resetFailures'])->whereUuid('route')->name('routes.reset-failures');
        Route::delete('routes/{route}', [ModelRouteController::class, 'destroy'])->whereUuid('route')->name('routes.destroy');
    });
});
