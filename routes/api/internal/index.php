<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    require __DIR__.'/auth.php';
    require __DIR__.'/workspaces.php';
    require __DIR__.'/triggers.php';
    require __DIR__.'/workflows.php';
    require __DIR__.'/workflow_builder.php';
    require __DIR__.'/runs.php';
    require __DIR__.'/agents.php';
    require __DIR__.'/skills.php';
});
