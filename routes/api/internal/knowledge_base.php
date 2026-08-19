<?php

use App\Http\Controllers\Api\Internal\V1\KnowledgeBase\KnowledgeBaseController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:api', 'workspace.context'])
    ->prefix('workspaces/{workspace}/knowledge-base')
    ->as('knowledge-base.')
    ->group(function () {
        Route::get('/', [KnowledgeBaseController::class, 'index'])->name('index');
        Route::post('/', [KnowledgeBaseController::class, 'store'])->name('store');
        Route::post('search', [KnowledgeBaseController::class, 'search'])->name('search');

        Route::get('collections', [KnowledgeBaseController::class, 'collections'])->name('collections.index');
        Route::delete('collections/{collection}', [KnowledgeBaseController::class, 'destroyCollection'])->name('collections.destroy');

        Route::delete('{documentEmbedding}', [KnowledgeBaseController::class, 'destroy'])->name('destroy');
    });
