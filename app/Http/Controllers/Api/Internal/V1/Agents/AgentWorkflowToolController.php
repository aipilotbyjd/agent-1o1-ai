<?php

namespace App\Http\Controllers\Api\Internal\V1\Agents;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Internal\V1\Workflows\WorkflowResource;
use App\Http\Responses\ApiResponse;
use App\Models\Agents\Agent;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;

class AgentWorkflowToolController extends Controller
{
    public function index(Workspace $workspace, Agent $agent)
    {
        $this->requirePermission(Permission::AgentView);
        $this->ensureBelongsToWorkspace($workspace, $agent);

        return ApiResponse::success([
            'workflows' => WorkflowResource::collection($agent->workflows),
        ]);
    }

    public function store(Workspace $workspace, Agent $agent, Workflow $workflow)
    {
        $this->requirePermission(Permission::AgentManage);
        $this->ensureBelongsToWorkspace($workspace, $agent);
        abort_if($workflow->workspace_id !== $workspace->id, 404);

        $agent->workflows()->syncWithoutDetaching([$workflow->id]);

        return ApiResponse::success(['workflows' => WorkflowResource::collection($agent->workflows()->get())], 'Workflow attached successfully.');
    }

    public function destroy(Workspace $workspace, Agent $agent, Workflow $workflow)
    {
        $this->requirePermission(Permission::AgentManage);
        $this->ensureBelongsToWorkspace($workspace, $agent);

        $agent->workflows()->detach($workflow->id);

        return ApiResponse::noContent();
    }
}
