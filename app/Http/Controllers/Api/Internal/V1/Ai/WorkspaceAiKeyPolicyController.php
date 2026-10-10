<?php

namespace App\Http\Controllers\Api\Internal\V1\Ai;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Ai\UpdateWorkspaceAiKeyPolicyRequest;
use App\Http\Resources\Api\Internal\V1\Ai\WorkspaceAiKeyPolicyResource;
use App\Http\Responses\ApiResponse;
use App\Models\Ai\WorkspaceAiKeyPolicy;
use App\Models\Workspaces\Workspace;

/**
 * The workspace's rules for its own AI provider keys — readable by anyone
 * who can see the keys (so they know why a model is unavailable or their
 * personal key isn't used), changeable only by an admin.
 */
class WorkspaceAiKeyPolicyController extends Controller
{
    public function show(Workspace $workspace)
    {
        $this->requirePermission(Permission::AiCredentialView);

        return ApiResponse::success(['policy' => WorkspaceAiKeyPolicyResource::make(WorkspaceAiKeyPolicy::forWorkspace($workspace->id))]);
    }

    public function update(UpdateWorkspaceAiKeyPolicyRequest $request, Workspace $workspace)
    {
        $this->requirePermission(Permission::AiCredentialManage);

        $policy = WorkspaceAiKeyPolicy::query()->updateOrCreate(['workspace_id' => $workspace->id], $request->validated());

        return ApiResponse::success(['policy' => WorkspaceAiKeyPolicyResource::make($policy)], 'AI key policy updated.');
    }
}
