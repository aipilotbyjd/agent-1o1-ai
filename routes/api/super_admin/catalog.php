<?php

use App\Http\Controllers\Api\SuperAdmin\V1\Catalog\SuperAdminCatalogController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'throttle:super-admin', 'auth:super-admin', 'super-admin.ip:account', 'super-admin.active',
    'super-admin.two-factor', 'super-admin.audit', 'super-admin.can:catalog.manage',
])->prefix('catalog')->as('catalog.')->group(function () {
    Route::get('connectors', [SuperAdminCatalogController::class, 'connectors'])->name('connectors.index');
    Route::post('connectors/{connector}/enable', [SuperAdminCatalogController::class, 'enableConnector'])->whereUuid('connector')->name('connectors.enable');
    Route::post('connectors/{connector}/disable', [SuperAdminCatalogController::class, 'disableConnector'])->whereUuid('connector')->name('connectors.disable');

    Route::get('nodes', [SuperAdminCatalogController::class, 'nodes'])->name('nodes.index');
    Route::post('nodes/{node}/enable', [SuperAdminCatalogController::class, 'enableNode'])->whereUuid('node')->name('nodes.enable');
    Route::post('nodes/{node}/disable', [SuperAdminCatalogController::class, 'disableNode'])->whereUuid('node')->name('nodes.disable');

    Route::get('app-categories', [SuperAdminCatalogController::class, 'appCategories'])->name('app-categories.index');
    Route::put('app-categories/{category}', [SuperAdminCatalogController::class, 'updateAppCategory'])->whereUuid('category')->name('app-categories.update');

    Route::get('templates/{type}', [SuperAdminCatalogController::class, 'templates'])->name('templates.index');
    Route::post('templates/{type}/{template}/publish', [SuperAdminCatalogController::class, 'publishTemplate'])->whereUuid('template')->name('templates.publish');
    Route::post('templates/{type}/{template}/unpublish', [SuperAdminCatalogController::class, 'unpublishTemplate'])->whereUuid('template')->name('templates.unpublish');
});
