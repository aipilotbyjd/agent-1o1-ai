<?php

use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::get('users', [UserController::class, 'index'])->name('users.index');
Route::get('users/{user}', [UserController::class, 'show'])->whereUuid('user')->name('users.show');
