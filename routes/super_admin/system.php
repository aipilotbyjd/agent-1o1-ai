<?php

use App\Http\Controllers\SuperAdmin\System\FailedJobController;
use App\Http\Controllers\SuperAdmin\System\QueueController;
use Illuminate\Support\Facades\Route;

Route::prefix('system')->as('system.')->group(function () {
    Route::get('queues', QueueController::class)->name('queues.index');

    Route::get('failed-jobs', [FailedJobController::class, 'index'])->name('failed-jobs.index');
    Route::get('failed-jobs/{uuid}', [FailedJobController::class, 'show'])->whereUuid('uuid')->name('failed-jobs.show');
    Route::post('failed-jobs/{uuid}/retry', [FailedJobController::class, 'retry'])->whereUuid('uuid')->name('failed-jobs.retry');
    Route::delete('failed-jobs/{uuid}', [FailedJobController::class, 'destroy'])->whereUuid('uuid')->name('failed-jobs.destroy');
});
