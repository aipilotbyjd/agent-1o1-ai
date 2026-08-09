<?php

use App\Http\Controllers\Api\Internal\V1\Agents\SkillController;
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
    });
