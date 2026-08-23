<?php

namespace App\Notifications\Billing;

use App\Enums\Notifications\NotificationEvent;
use App\Models\Workspaces\Workspace;
use App\Notifications\Workspace\WorkspaceEventNotification;

/**
 * The customer-facing sibling of `DeductCreditsAction`'s
 * `usage.threshold_crossed` admin alert — same crossing, same throttle, but
 * telling the workspace instead of the operator.
 */
class CreditsLowNotification extends WorkspaceEventNotification
{
    public function __construct(Workspace $workspace, int $creditsUsed, int $creditsLimit, int $percentUsed)
    {
        parent::__construct(
            workspace: $workspace,
            event: NotificationEvent::CreditsLow,
            title: "{$workspace->name} is running low on credits",
            body: "{$creditsUsed} of {$creditsLimit} credits used this period ({$percentUsed}%). Consider a top-up or an upgrade before you run out.",
            data: [
                'workspace_id' => $workspace->id,
                'credits_used' => $creditsUsed,
                'credits_limit' => $creditsLimit,
                'percent_used' => $percentUsed,
            ],
        );
    }
}
