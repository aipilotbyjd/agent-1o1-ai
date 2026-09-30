<?php

namespace App\Services\Agents\Approvals;

use App\Authorization\WorkspaceContext;
use App\Enums\Workspaces\Permission;
use App\Enums\Workspaces\Role;
use App\Models\Agents\AgentAction;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Models\Workspaces\WorkspaceMember;
use Illuminate\Support\Collection;

/**
 * Who may decide an action, and who is told it's waiting.
 *
 * A tool rule can name its approvers (`user:<id>`, `role:<role>`); then only
 * they decide — plus the workspace's owners and admins, who could change the
 * rule anyway. Without a list: the person the conversation belongs to, and
 * anyone whose role grants `agent.approve`.
 */
class ActionApprovers
{
    public function canDecide(User $user, AgentAction $action): bool
    {
        $workspace = $action->workspace;
        $role = WorkspaceContext::resolveRole($workspace, $user);

        if ($role === null) {
            return false;
        }

        if (in_array($role, [Role::Owner, Role::Admin], true)) {
            return true;
        }

        $approvers = $action->approvers ?? [];

        if ($approvers !== []) {
            return in_array("user:{$user->id}", $approvers, true) || in_array("role:{$role->value}", $approvers, true);
        }

        return $action->session?->user_id === $user->id || $role->has(Permission::AgentApprove);
    }

    /**
     * Everyone who should hear about these actions waiting.
     *
     * @param  Collection<int, AgentAction>  $actions
     * @return Collection<int, User>
     */
    public function recipientsFor(Workspace $workspace, Collection $actions): Collection
    {
        $members = $workspace->users()
            ->whereNull((new WorkspaceMember)->qualifyColumn('deleted_at'))
            ->get();

        $named = $actions->flatMap(fn (AgentAction $action): array => $action->approvers ?? [])->unique()->values();
        $ownerIds = $actions->map(fn (AgentAction $action): ?string => $action->session?->user_id)->filter()->unique();

        return $members
            ->filter(function (User $user) use ($workspace, $named, $ownerIds): bool {
                $role = $workspace->owner_id === $user->id ? Role::Owner : Role::tryFrom((string) $user->pivot?->role);

                if ($role === null) {
                    return false;
                }

                if ($named->isNotEmpty()) {
                    return in_array($role, [Role::Owner, Role::Admin], true)
                        || $named->contains("user:{$user->id}")
                        || $named->contains("role:{$role->value}");
                }

                return $ownerIds->contains($user->id) || in_array($role, [Role::Owner, Role::Admin], true);
            })
            ->values();
    }
}
