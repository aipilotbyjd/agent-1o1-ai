<?php

use App\Http\Controllers\Admin\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::get('workspaces', [WorkspaceController::class, 'index'])->name('workspaces.index');
Route::get('workspaces/{workspace}', [WorkspaceController::class, 'show'])->whereUuid('workspace')->withTrashed()->name('workspaces.show');
