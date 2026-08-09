<?php

namespace App\Http\Controllers\Api\Internal\V1\Workflows;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Workflows\StoreWorkflowRequest;
use App\Http\Requests\Api\Internal\V1\Workflows\UpdateWorkflowRequest;
use App\Http\Resources\Api\Internal\V1\Workflows\WorkflowResource;
use App\Http\Responses\ApiResponse;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use Illuminate\Support\Str;

class WorkflowController extends Controller
{
    public function index(Workspace $workspace)
    {
        $this->requirePermission(Permission::WorkflowView);

        return ApiResponse::success([
            'workflows' => WorkflowResource::collection($workspace->workflows()->latest()->get()),
        ]);
    }

    public function store(StoreWorkflowRequest $request, Workspace $workspace)
    {
        $this->requirePermission(Permission::WorkflowManage);

        $workflow = $workspace->workflows()->create([
            ...$request->validated(),
            'slug' => $request->validated('slug') ?: Str::slug($request->validated('name')).'-'.Str::random(6),
            'created_by' => $request->user()->id,
        ]);

        return ApiResponse::created(['workflow' => WorkflowResource::make($workflow)], 'Workflow created successfully.');
    }

    public function show(Workspace $workspace, Workflow $workflow)
    {
        $this->requirePermission(Permission::WorkflowView);
        $this->ensureBelongsToWorkspace($workspace, $workflow);

        return ApiResponse::success([
            'workflow' => WorkflowResource::make($workflow->load(['nodes', 'edges'])),
        ]);
    }

    public function update(UpdateWorkflowRequest $request, Workspace $workspace, Workflow $workflow)
    {
        $this->requirePermission(Permission::WorkflowManage);
        $this->ensureBelongsToWorkspace($workspace, $workflow);

        $workflow->update($request->validated());

        return ApiResponse::success(['workflow' => WorkflowResource::make($workflow)], 'Workflow updated successfully.');
    }

    public function destroy(Workspace $workspace, Workflow $workflow)
    {
        $this->requirePermission(Permission::WorkflowManage);
        $this->ensureBelongsToWorkspace($workspace, $workflow);

        $workflow->delete();

        return ApiResponse::noContent();
    }
}
