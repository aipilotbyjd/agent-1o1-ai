<?php

namespace App\Notifications\Workspace;

use App\Enums\Notifications\NotificationEvent;
use App\Models\Runs\Run;
use App\Models\Workspaces\Workspace;

class RunFailedNotification extends WorkspaceEventNotification
{
    public function __construct(Workspace $workspace, Run $run)
    {
        parent::__construct(
            workspace: $workspace,
            event: NotificationEvent::RunFailed,
            title: "A run failed in {$workspace->name}",
            body: $run->error !== null ? "Run {$run->id} failed: {$run->error}" : "Run {$run->id} failed.",
            data: [
                'run_id' => $run->id,
                'workflow_id' => $run->workflow_id,
                'error' => $run->error,
            ],
        );
    }
}
