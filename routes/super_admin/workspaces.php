<?php

use App\Http\Controllers\SuperAdmin\WorkspaceBillingController;
use App\Http\Controllers\SuperAdmin\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::get('workspaces', [WorkspaceController::class, 'index'])->name('workspaces.index');
Route::get('workspaces/{workspace}', [WorkspaceController::class, 'show'])->whereUuid('workspace')->withTrashed()->name('workspaces.show');

/*
| Billing changes. Deleted workspaces are not bound here, so they 404.
*/
Route::prefix('workspaces/{workspace}')->whereUuid('workspace')->as('workspaces.')->group(function () {
    Route::post('credits', [WorkspaceBillingController::class, 'grantCredits'])->name('credits.grant');
    Route::post('plan-time', [WorkspaceBillingController::class, 'grantPlanTime'])->name('plan-time.grant');
    Route::post('plan-grants/{grant}/revoke', [WorkspaceBillingController::class, 'revokePlanGrant'])->whereUuid('grant')->name('plan-grants.revoke');
});
