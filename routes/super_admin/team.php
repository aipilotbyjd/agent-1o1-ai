<?php

use App\Http\Controllers\SuperAdmin\TeamController;
use Illuminate\Support\Facades\Route;

Route::get('team', [TeamController::class, 'index'])->name('team.index');
