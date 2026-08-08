<?php

namespace App\Http\Controllers\Api\Internal\V1\Workspaces;

use App\Enums\Workspaces\Permission;
use App\Enums\Workspaces\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Workspaces\InviteMemberRequest;
use App\Http\Resources\Api\Internal\V1\Workspaces\WorkspaceInvitationResource;
use App\Models\Workspaces\Workspace;
use App\Models\Workspaces\WorkspaceInvitation;
use App\Services\Workspaces\WorkspaceInvitationService;
use Illuminate\Http\Request;

class WorkspaceInvitationController extends Controller
{
    public function __construct(
        private readonly WorkspaceInvitationService $invitations,
    ) {}

    public function index(Workspace $workspace)
    {
        $this->requirePermission(Permission::InvitationView);

        return response()->json(['invitations' => WorkspaceInvitationResource::collection($workspace->invitations()->whereNull('accepted_at')->get())]);
    }

    public function store(InviteMemberRequest $request, Workspace $workspace)
    {
        $this->requirePermission(Permission::MemberInvite);

        $invitation = $this->invitations->invite(
            $workspace,
            $request->validated('email'),
            Role::from($request->validated('role')),
            $request->user(),
        );

        return response()->json(['invitation' => WorkspaceInvitationResource::make($invitation)], 201);
    }

    public function destroy(Workspace $workspace, WorkspaceInvitation $invitation)
    {
        $this->requirePermission(Permission::MemberInvite);
        $this->ensureBelongsToWorkspace($workspace, $invitation);

        $this->invitations->revoke($invitation);

        return response()->noContent();
    }

    public function accept(Request $request, WorkspaceInvitation $invitation)
    {
        $this->invitations->accept($invitation, $request->user());

        return response()->json(['message' => 'Invitation accepted.']);
    }
}
