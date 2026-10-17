<?php

namespace App\Http\Controllers\SuperAdmin\System;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\ConfirmSupportActionRequest;
use App\Services\SuperAdmin\SuperAdminFailedJobs;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FailedJobController extends Controller
{
    public function __construct(private readonly SuperAdminFailedJobs $failedJobs) {}

    public function index(Request $request): View
    {
        $queue = $request->validate(['queue' => ['nullable', 'string', 'max:100']])['queue'] ?? null;

        return view('super-admin.system.failed-jobs.index', [
            'jobs' => $this->failedJobs->paginate($queue),
            'queues' => $this->failedJobs->failedQueues(),
            'filters' => ['queue' => $queue],
        ]);
    }

    public function show(string $uuid): View
    {
        $job = $this->failedJobs->find($uuid);

        abort_if($job === null, 404);

        return view('super-admin.system.failed-jobs.show', ['job' => $job]);
    }

    public function retry(ConfirmSupportActionRequest $request, string $uuid): RedirectResponse
    {
        abort_unless($this->failedJobs->retry($uuid, $request->user('super_admin')), 404);

        return redirect()->route('super-admin.system.failed-jobs.index')->with('status', 'Job pushed back onto its queue.');
    }

    public function destroy(ConfirmSupportActionRequest $request, string $uuid): RedirectResponse
    {
        abort_unless($this->failedJobs->forget($uuid, $request->user('super_admin')), 404);

        return redirect()->route('super-admin.system.failed-jobs.index')->with('status', 'Failed job deleted.');
    }
}
