<?php

use App\Http\Controllers\Admin\RunController;
use Illuminate\Support\Facades\Route;

Route::get('runs', [RunController::class, 'index'])->name('runs.index');
Route::get('runs/{run}', [RunController::class, 'show'])->whereUuid('run')->name('runs.show');
