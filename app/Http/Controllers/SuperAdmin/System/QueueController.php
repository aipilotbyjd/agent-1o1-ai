<?php

namespace App\Http\Controllers\SuperAdmin\System;

use App\Http\Controllers\Controller;
use App\Services\SuperAdmin\SuperAdminFailedJobs;
use Illuminate\View\View;

class QueueController extends Controller
{
    public function __invoke(SuperAdminFailedJobs $failedJobs): View
    {
        return view('super-admin.system.queues', [
            'sizes' => $failedJobs->queueSizes(),
            'failedCounts' => $failedJobs->failedCountsByQueue(),
            'connection' => config('queue.default'),
        ]);
    }
}
