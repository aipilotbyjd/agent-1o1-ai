<?php

namespace App\Http\Controllers\Api\Internal\V1\Workflows;

use App\Enums\Billing\PlanLimit;
use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Workflows\StoreWorkflowRequest;
use App\Http\Requests\Api\Internal\V1\Workflows\UpdateWorkflowRequest;
use App\Http\Resources\Api\Internal\V1\Workflows\WorkflowResource;
use App\Http\Responses\ApiResponse;
use App\Models\Workflows\Workflow;
use App\Models\Workflows\WorkflowEdge;
use App\Models\Workflows\WorkflowNode;
use App\Models\Workspaces\Workspace;
use App\Services\Billing\PlanLimitGate;
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

    public function store(StoreWorkflowRequest $request, Workspace $workspace, PlanLimitGate $limits)
    {
        $this->requirePermission(Permission::WorkflowManage);
        $limits->assertCanCreate($workspace, PlanLimit::Workflows);

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

    /**
     * Copy a workflow into a new draft in the same workspace — name suffixed
     * with "(copy)", same folder, current graph carried over as-is (unlike
     * `WorkflowTemplate::storeFromWorkflow()`, credentials don't need
     * stripping here since the copy never leaves the source workspace).
     */
    public function duplicate(Workspace $workspace, Workflow $workflow, PlanLimitGate $limits)
    {
        $this->requirePermission(Permission::WorkflowManage);
        $this->ensureBelongsToWorkspace($workspace, $workflow);
        $limits->assertCanCreate($workspace, PlanLimit::Workflows);

        $name = "{$workflow->name} (copy)";

        $duplicate = $workspace->workflows()->create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(6),
            'folder_id' => $workflow->folder_id,
            'description' => $workflow->description,
            'created_by' => request()->user()->id,
        ]);

        $duplicate->replaceGraph($this->currentGraph($workflow));

        return ApiResponse::created(['workflow' => WorkflowResource::make($duplicate->load(['nodes', 'edges']))], 'Workflow duplicated successfully.');
    }

    /**
     * @return array{nodes: array<int, array<string, mixed>>, edges: array<int, array<string, mixed>>}
     */
    private function currentGraph(Workflow $workflow): array
    {
        $workflow->loadMissing(['nodes', 'edges']);

        $nodeKeysById = $workflow->nodes->pluck('key', 'id');

        return [
            'nodes' => $workflow->nodes
                ->map(fn (WorkflowNode $node): array => [
                    'key' => $node->key,
                    'type' => $node->type,
                    'config' => $node->config ?? [],
                    'position' => $node->position,
                ])
                ->all(),
            'edges' => $workflow->edges
                ->map(fn (WorkflowEdge $edge): array => [
                    'from' => $nodeKeysById[$edge->from_node_id],
                    'to' => $nodeKeysById[$edge->to_node_id],
                    'condition' => $edge->condition,
                ])
                ->all(),
        ];
    }
}
