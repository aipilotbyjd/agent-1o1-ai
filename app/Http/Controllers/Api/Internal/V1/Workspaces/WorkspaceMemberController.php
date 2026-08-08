<?php

namespace App\Http\Controllers\Api\Internal\V1\Workspaces;

use App\Enums\Workspaces\Permission;
use App\Enums\Workspaces\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Workspaces\UpdateMemberRoleRequest;
use App\Http\Resources\Api\Internal\V1\Workspaces\WorkspaceMemberResource;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Http\Request;

class WorkspaceMemberController extends Controller
{
    public function __construct(
        private readonly WorkspaceService $workspaces,
    ) {}

    public function index(Workspace $workspace)
    {
        $this->requirePermission(Permission::MemberView);

        return response()->json(['members' => WorkspaceMemberResource::collection($workspace->members()->with('user')->get())]);
    }

    public function updateRole(UpdateMemberRoleRequest $request, Workspace $workspace, User $member)
    {
        $this->requirePermission(Permission::MemberUpdateRole);

        $this->workspaces->updateMemberRole($workspace, $member, Role::from($request->validated('role')));

        return response()->noContent();
    }

    public function destroy(Workspace $workspace, User $member)
    {
        $this->requirePermission(Permission::MemberRemove);

        $this->workspaces->removeMember($workspace, $member);

        return response()->noContent();
    }

    public function leave(Request $request, Workspace $workspace)
    {
        $this->workspaces->leave($workspace, $request->user());

        return response()->noContent();
    }
}
