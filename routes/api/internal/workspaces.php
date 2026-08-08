<?php

use App\Http\Controllers\Api\Internal\V1\Workspaces\WorkspaceController;
use App\Http\Controllers\Api\Internal\V1\Workspaces\WorkspaceInvitationController;
use App\Http\Controllers\Api\Internal\V1\Workspaces\WorkspaceMemberController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:api')->prefix('workspaces')->group(function () {
    Route::get('/', [WorkspaceController::class, 'index']);
    Route::post('/', [WorkspaceController::class, 'store']);

    Route::get('invitations/{invitation}/accept', [WorkspaceInvitationController::class, 'accept'])
        ->middleware('signed')
        ->name('workspaces.invitations.accept');

    Route::middleware('workspace.context')->prefix('{workspace}')->group(function () {
        Route::get('/', [WorkspaceController::class, 'show']);
        Route::patch('/', [WorkspaceController::class, 'update']);
        Route::delete('/', [WorkspaceController::class, 'destroy']);

        Route::get('members', [WorkspaceMemberController::class, 'index']);
        Route::patch('members/{member}', [WorkspaceMemberController::class, 'updateRole']);
        Route::delete('members/{member}', [WorkspaceMemberController::class, 'destroy']);
        Route::post('leave', [WorkspaceMemberController::class, 'leave']);

        Route::get('invitations', [WorkspaceInvitationController::class, 'index']);
        Route::post('invitations', [WorkspaceInvitationController::class, 'store']);
        Route::delete('invitations/{invitation}', [WorkspaceInvitationController::class, 'destroy']);
    });
});
