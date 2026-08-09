<?php

namespace App\Listeners\Workflows;

use App\Events\Runs\RunFailed;
use Illuminate\Support\Facades\Log;

/**
 * Stub — real workspace-owner notifications land alongside
 * `HumanApproval`'s `RunApprovalRequestedNotification` port
 * (docs/WORKFLOWS_AGENTS_BUILD_PLAN.md Stage 4).
 */
class NotifyOnRunFailure
{
    public function handle(RunFailed $event): void
    {
        Log::warning('Run failed.', [
            'run_id' => $event->run->id,
            'workspace_id' => $event->run->workspace_id,
            'error' => $event->run->error,
        ]);
    }
}
