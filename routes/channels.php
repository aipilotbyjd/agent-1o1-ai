<?php

use App\Broadcasting\Channels;
use App\Broadcasting\WorkspaceChannelGate;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
 * User ids are UUIDs, so they are compared exactly as strings. Casting to int
 * kept only the leading digits ("01a0…" → 1) and let nearly any user
 * subscribe to any other user's channel.
 */
Broadcast::channel('App.Models.User.{id}', function (User $user, string $id): bool {
    return hash_equals((string) $user->getKey(), $id);
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

Broadcast::channel(
    Channels::WORKFLOW_BUILDER_SESSION_PATTERN,
    fn (User $user, string $workspaceId, string $sessionId): bool => app(WorkspaceChannelGate::class)->workflowBuilderSession($user, $workspaceId, $sessionId),
);

Broadcast::channel(
    Channels::ASSISTANT_SESSION_PATTERN,
    fn (User $user, string $workspaceId, string $sessionId): bool => app(WorkspaceChannelGate::class)->assistantSession($user, $workspaceId, $sessionId),
);

Broadcast::channel(
    Channels::ASSISTANT_PATTERN,
    fn (User $user, string $workspaceId, string $assistantId): bool => app(WorkspaceChannelGate::class)->assistant($user, $workspaceId, $assistantId),
);
