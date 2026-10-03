<?php

use App\Broadcasting\Channels;
use App\Broadcasting\WorkspaceChannelGate;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (string) $user->id === (string) $id;
});

/*
 * Live run and agent-chat streams. The channel names come from
 * `App\Broadcasting\Channels` (shared with the events that publish to them)
 * and the rules from `App\Broadcasting\WorkspaceChannelGate`, so neither can
 * drift out of step with the other.
 */
Broadcast::channel(
    Channels::WORKSPACE_RUNS_PATTERN,
    fn (User $user, string $workspaceId): bool => app(WorkspaceChannelGate::class)->runs($user, $workspaceId),
);

Broadcast::channel(
    Channels::RUN_PATTERN,
    fn (User $user, string $workspaceId, string $runId): bool => app(WorkspaceChannelGate::class)->run($user, $workspaceId, $runId),
);

Broadcast::channel(
    Channels::AGENT_SESSION_PATTERN,
    fn (User $user, string $workspaceId, string $sessionId): bool => app(WorkspaceChannelGate::class)->agentSession($user, $workspaceId, $sessionId),
);

Broadcast::channel(
    Channels::WORKFLOW_BUILDER_SESSION_PATTERN,
    fn (User $user, string $workspaceId, string $sessionId): bool => app(WorkspaceChannelGate::class)->workflowBuilderSession($user, $workspaceId, $sessionId),
);
