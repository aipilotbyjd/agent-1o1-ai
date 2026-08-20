<?php

namespace App\Broadcasting;

use App\Models\Agents\AgentSession;
use App\Models\Runs\Run;

/**
 * The one place channel names are spelled out. Both halves of broadcasting —
 * the events that publish (`Events\Runs\*`, `Events\Agents\*`) and the
 * authorization callbacks that gate subscription (`routes/channels.php`) —
 * build their names from here, so a rename can't leave a channel that is
 * published to but never authorized (or vice versa).
 *
 * Everything is workspace-scoped and private: run output and agent replies
 * routinely carry customer data, so there is no presence or public tier.
 */
final class Channels
{
    /**
     * Routing patterns for `routes/channels.php`. Kept beside the builders
     * below so the authorization side and the publishing side are edited
     * together — Laravel needs a literal pattern with `{...}` placeholders to
     * match against, which is why these can't just be the builders' output.
     */
    public const string WORKSPACE_RUNS_PATTERN = 'workspaces.{workspaceId}.runs';

    public const string RUN_PATTERN = 'workspaces.{workspaceId}.runs.{runId}';

    public const string AGENT_SESSION_PATTERN = 'workspaces.{workspaceId}.agent-sessions.{sessionId}';

    /**
     * Workspace-wide firehose of run state transitions — what a "Runs" list
     * subscribes to. Individual node steps do *not* land here; they would
     * flood a list view that only renders one row per run.
     */
    public static function workspaceRuns(int $workspaceId): string
    {
        return "workspaces.{$workspaceId}.runs";
    }

    /**
     * One run's own stream: its status transitions plus every node step —
     * what an open run canvas subscribes to.
     */
    public static function run(Run $run): string
    {
        return "workspaces.{$run->workspace_id}.runs.{$run->id}";
    }

    /**
     * One agent conversation's stream — messages as they are appended.
     */
    public static function agentSession(AgentSession $session): string
    {
        return "workspaces.{$session->workspace_id}.agent-sessions.{$session->id}";
    }
}
