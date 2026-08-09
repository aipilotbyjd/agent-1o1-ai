<?php

namespace App\Http\Controllers\Api\Internal\V1\Workflows;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Workflows\PublishWorkflowRequest;
use App\Http\Resources\Api\Internal\V1\Workflows\WorkflowResource;
use App\Http\Resources\Api\Internal\V1\Workflows\WorkflowVersionResource;
use App\Http\Responses\ApiResponse;
use App\Models\Workflows\Workflow;
use App\Models\Workflows\WorkflowVersion;
use App\Models\Workspaces\Workspace;

class WorkflowVersionController extends Controller
{
    public function index(Workspace $workspace, Workflow $workflow)
    {
        $this->requirePermission(Permission::WorkflowVersion);
        $this->ensureBelongsToWorkspace($workspace, $workflow);

        return ApiResponse::success([
            'versions' => WorkflowVersionResource::collection($workflow->versions()->latest('version')->get()),
        ]);
    }

    public function show(Workspace $workspace, Workflow $workflow, WorkflowVersion $version)
    {
        $this->requirePermission(Permission::WorkflowVersion);
        $this->ensureBelongsToWorkspace($workspace, $workflow);
        abort_if($version->workflow_id !== $workflow->id, 404);

        return ApiResponse::success(['version' => WorkflowVersionResource::make($version)]);
    }

    /**
     * Validate the current draft (`GraphValidator`, the full sequence) and,
     * if it passes, snapshot it as a new immutable version — see
     * `Workflow::publishVersion()`.
     */
    public function store(PublishWorkflowRequest $request, Workspace $workspace, Workflow $workflow)
    {
        $this->requirePermission(Permission::WorkflowPublish);
        $this->ensureBelongsToWorkspace($workspace, $workflow);

        $version = $workflow->publishVersion($request->validated('notes'), $request->user());

        return ApiResponse::created([
            'version' => WorkflowVersionResource::make($version),
            'workflow' => WorkflowResource::make($workflow->fresh()),
        ], 'Workflow published successfully.');
    }
}
