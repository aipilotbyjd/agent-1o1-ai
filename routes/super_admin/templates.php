<?php

use App\Http\Controllers\SuperAdmin\Templates\CollectionController;
use App\Http\Controllers\SuperAdmin\Templates\TemplateController;
use Illuminate\Support\Facades\Route;

Route::prefix('templates')->as('templates.')->group(function () {
    Route::get('collections', [CollectionController::class, 'index'])->name('collections.index');
    Route::get('collections/create', [CollectionController::class, 'create'])->name('collections.create');
    Route::post('collections', [CollectionController::class, 'store'])->name('collections.store');

    Route::prefix('collections/{collection}')->whereUuid('collection')->as('collections.')->group(function () {
        Route::get('/', [CollectionController::class, 'show'])->name('show');
        Route::get('edit', [CollectionController::class, 'edit'])->name('edit');
        Route::put('/', [CollectionController::class, 'update'])->name('update');
        Route::delete('/', [CollectionController::class, 'destroy'])->name('destroy');
        Route::post('publish', [CollectionController::class, 'publish'])->name('publish');
        Route::post('items', [CollectionController::class, 'addItem'])->name('items.store');
        Route::delete('items/{item}', [CollectionController::class, 'removeItem'])->whereUuid('item')->name('items.destroy');
        Route::post('items/{item}/move', [CollectionController::class, 'moveItem'])->whereUuid('item')->name('items.move');
    });

    Route::get('/', [TemplateController::class, 'index'])->name('index');

    Route::prefix('{kind}/{template}')->whereIn('kind', ['workflow', 'agent'])->whereUuid('template')->group(function () {
        Route::get('edit', [TemplateController::class, 'edit'])->name('edit');
        Route::put('/', [TemplateController::class, 'update'])->name('update');
        Route::delete('/', [TemplateController::class, 'destroy'])->name('destroy');
        Route::post('publish', [TemplateController::class, 'publish'])->name('publish');
        Route::post('copy-to-gallery', [TemplateController::class, 'copyToGallery'])->name('copy-to-gallery');
    });
});
