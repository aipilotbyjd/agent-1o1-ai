<?php

use App\Http\Controllers\Api\Internal\V1\Agents\SkillController;
use App\Http\Controllers\Api\Internal\V1\Agents\SkillReferenceController;
use App\Http\Controllers\Api\Internal\V1\Agents\SkillScriptController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:api', 'workspace.context'])
    ->prefix('workspaces/{workspace}/skills')
    ->as('skills.')
    ->group(function () {
        Route::get('/', [SkillController::class, 'index'])->name('index');
        Route::post('/', [SkillController::class, 'store'])->name('store');
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
