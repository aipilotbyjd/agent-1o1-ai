<?php

namespace App\Broadcasting;

use App\Authorization\WorkspaceContext;
use App\Enums\Workspaces\Permission;
use App\Models\Agents\AgentSession;
use App\Models\Runs\Run;
use App\Models\User;
use App\Models\Workspaces\Workspace;

/**
 * Who may subscribe to what. Lives here rather than as closures inside
 * `routes/channels.php` so the rules can be unit-tested directly, the way
 * every other authorization decision in the app can be.
 *
 * The rule throughout: a channel requires the same workspace permission as
 * the REST endpoint carrying the same data. Someone who may not read a run
 * over HTTP must not be able to watch it arrive over a socket instead.
 */
class WorkspaceChannelGate
{
    public function runs(User $user, int $workspaceId): bool
    {
        return $this->allows($user, $workspaceId, Permission::RunView);
    }

    public function run(User $user, int $workspaceId, int $runId): bool
    {
        if (! $this->allows($user, $workspaceId, Permission::RunView)) {
            return false;
        }

        // Belt and braces: a member of workspace A must not be able to watch
        // a run living in workspace B by splicing its id into A's channel
        // name. The same check `ensureBelongsToWorkspace()` makes over HTTP.
        return Run::whereKey($runId)->where('workspace_id', $workspaceId)->exists();
    }

    public function agentSession(User $user, int $workspaceId, int $sessionId): bool
    {
        if (! $this->allows($user, $workspaceId, Permission::AgentView)) {
            return false;
        }

        return AgentSession::whereKey($sessionId)->where('workspace_id', $workspaceId)->exists();
    }

    private function allows(User $user, int $workspaceId, Permission $permission): bool
    {
        $workspace = Workspace::find($workspaceId);

        if ($workspace === null) {
            return false;
        }

        return WorkspaceContext::resolveRole($workspace, $user)?->has($permission) ?? false;
    }
}
