<?php

namespace App\Notifications\Workspace;

use App\Enums\Notifications\NotificationEvent;
use App\Models\Runs\Run;
use App\Models\Workspaces\Workspace;

class RunFailedNotification extends WorkspaceEventNotification
{
    public function __construct(Workspace $workspace, Run $run, ?string $failedNodeKey = null)
    {
        $label = $run->workflow?->name ?? "Run {$run->id}";

        parent::__construct(
            workspace: $workspace,
            event: NotificationEvent::RunFailed,
            title: "{$label} failed in {$workspace->name}",
            // `runs.error` is already secret-redacted by `StepFailureHandler`
            // before it is persisted, so it is safe to put in an email body.
            body: $run->error ?? "Workflow run {$run->id} failed.",
            data: [
                'run_id' => $run->id,
                'workflow_id' => $run->workflow_id,
                'node_key' => $failedNodeKey,
                'trigger_type' => $run->trigger_type,
            ],
        );
    }
}
