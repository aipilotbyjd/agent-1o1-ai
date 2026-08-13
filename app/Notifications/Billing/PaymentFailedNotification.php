<?php

namespace App\Notifications\Billing;

use App\Enums\Notifications\NotificationEvent;
use App\Models\Workspaces\Workspace;
use App\Notifications\Workspace\WorkspaceEventNotification;

class PaymentFailedNotification extends WorkspaceEventNotification
{
    public function __construct(Workspace $workspace)
    {
        parent::__construct(
            workspace: $workspace,
            event: NotificationEvent::PaymentFailed,
            title: "A payment for {$workspace->name} failed",
            body: 'Stripe could not charge your payment method. Update your billing details to avoid losing access to your plan.',
            data: [
                'workspace_id' => $workspace->id,
            ],
        );
    }
}
