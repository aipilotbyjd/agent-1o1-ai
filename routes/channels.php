<?php

use App\Broadcasting\Channels;
use App\Broadcasting\WorkspaceChannelGate;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/*
 * Live run and agent-chat streams. The channel names come from
 * `App\Broadcasting\Channels` (shared with the events that publish to them)
 * and the rules from `App\Broadcasting\WorkspaceChannelGate`, so neither can
 * drift out of step with the other.
 */
Broadcast::channel(
    Channels::WORKSPACE_RUNS_PATTERN,
    fn (User $user, int $workspaceId): bool => app(WorkspaceChannelGate::class)->runs($user, $workspaceId),
);

Broadcast::channel(
    Channels::RUN_PATTERN,
    fn (User $user, int $workspaceId, int $runId): bool => app(WorkspaceChannelGate::class)->run($user, $workspaceId, $runId),
);

Broadcast::channel(
    Channels::AGENT_SESSION_PATTERN,
    fn (User $user, int $workspaceId, int $sessionId): bool => app(WorkspaceChannelGate::class)->agentSession($user, $workspaceId, $sessionId),
);
