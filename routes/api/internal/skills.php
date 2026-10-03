<?php

use App\Http\Controllers\Api\Internal\V1\Agents\SkillController;
use App\Http\Controllers\Api\Internal\V1\Agents\SkillDraftController;
use App\Http\Controllers\Api\Internal\V1\Agents\SkillReferenceController;
use App\Http\Controllers\Api\Internal\V1\Agents\SkillScriptController;
use App\Http\Controllers\Api\Internal\V1\Agents\SkillSourceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:api', 'workspace.context'])
    ->prefix('workspaces/{workspace}/skills')
    ->as('skills.')
    ->group(function () {
        Route::get('/', [SkillController::class, 'index'])->name('index');
        Route::post('/', [SkillController::class, 'store'])->name('store');
        Route::post('draft', SkillDraftController::class)->name('draft');
        Route::get('{skill}', [SkillController::class, 'show'])->name('show');
        Route::patch('{skill}', [SkillController::class, 'update'])->name('update');
        Route::delete('{skill}', [SkillController::class, 'destroy'])->name('destroy');

        Route::prefix('{skill}/references')->as('references.')->group(function (): void {
            Route::get('/', [SkillReferenceController::class, 'index'])->name('index');
            Route::post('/', [SkillReferenceController::class, 'store'])->name('store');
            Route::patch('{reference}', [SkillReferenceController::class, 'update'])->name('update');
            Route::delete('{reference}', [SkillReferenceController::class, 'destroy'])->name('destroy');
        });

        Route::prefix('{skill}/scripts')->as('scripts.')->group(function (): void {
            Route::get('/', [SkillScriptController::class, 'index'])->name('index');
            Route::post('/', [SkillScriptController::class, 'store'])->name('store');
            Route::patch('{script}', [SkillScriptController::class, 'update'])->name('update');
            Route::delete('{script}', [SkillScriptController::class, 'destroy'])->name('destroy');
        });
    });

Route::middleware(['auth:api', 'workspace.context'])
    ->prefix('workspaces/{workspace}/skill-sources')
    ->as('skill-sources.')
    ->group(function () {
        Route::get('/', [SkillSourceController::class, 'index'])->name('index');
        Route::post('preview', [SkillSourceController::class, 'preview'])->middleware('throttle:20,1')->name('preview');
        Route::post('/', [SkillSourceController::class, 'store'])->middleware('throttle:30,1')->name('store');
        Route::post('{skillSource}/sync', [SkillSourceController::class, 'sync'])->middleware('throttle:10,1')->name('sync');
        Route::delete('{skillSource}', [SkillSourceController::class, 'destroy'])->name('destroy');
    });
