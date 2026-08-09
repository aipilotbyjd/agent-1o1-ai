<?php

namespace App\Http\Controllers\Api\Internal\V1\Workflows;

use App\Actions\Workflows\Builder\CreateWorkflowBuilderSessionAction;
use App\Actions\Workflows\Builder\PromoteWorkflowBuilderSessionAction;
use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Workflows\PromoteWorkflowBuilderSessionRequest;
use App\Http\Requests\Api\Internal\V1\Workflows\StoreWorkflowBuilderSessionRequest;
use App\Http\Resources\Api\Internal\V1\Workflows\WorkflowBuilderSessionResource;
use App\Http\Resources\Api\Internal\V1\Workflows\WorkflowResource;
use App\Http\Responses\ApiResponse;
use App\Models\Workflows\Builder\WorkflowBuilderSession;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;

/**
 * The chat-based workflow builder — sessions here own a `draft_graph` edited
 * through `WorkflowBuilderAgent`'s tools (see `WorkflowBuilderMessageController`).
 * Separate from `WorkflowBuilderController`, which is the direct
 * editor-autosave endpoint on an already-created `Workflow`.
 */
class WorkflowBuilderSessionController extends Controller
{
    public function __construct(
        private readonly CreateWorkflowBuilderSessionAction $createSession,
        private readonly PromoteWorkflowBuilderSessionAction $promoteSession,
    ) {}

    public function index(Workspace $workspace)
    {
        $this->requirePermission(Permission::WorkflowBuilderUse);

        return ApiResponse::success([
            'sessions' => WorkflowBuilderSessionResource::collection($workspace->builderSessions()->latest()->get()),
        ]);
    }

    public function store(StoreWorkflowBuilderSessionRequest $request, Workspace $workspace)
    {
        $this->requirePermission(Permission::WorkflowBuilderUse);

        $workflow = null;

        if ($request->validated('workflow_id')) {
            $workflow = Workflow::findOrFail($request->validated('workflow_id'));
            $this->ensureBelongsToWorkspace($workspace, $workflow);
        }

        $session = $this->createSession->execute($workspace, $request->user(), $request->validated('title'), $workflow);

        return ApiResponse::created(['session' => WorkflowBuilderSessionResource::make($session)], 'Session created successfully.');
    }

    public function show(Workspace $workspace, WorkflowBuilderSession $session)
    {
        $this->requirePermission(Permission::WorkflowBuilderUse);
        $this->ensureBelongsToWorkspace($workspace, $session);

        return ApiResponse::success([
            'session' => WorkflowBuilderSessionResource::make($session->load('messages')),
        ]);
    }

    public function destroy(Workspace $workspace, WorkflowBuilderSession $session)
    {
        $this->requirePermission(Permission::WorkflowBuilderUse);
        $this->ensureBelongsToWorkspace($workspace, $session);

        $session->delete();

        return ApiResponse::noContent();
    }

    /**
     * Publish the draft graph to a real, workspace-visible `Workflow` — a
     * new one unless the session was already started from (or already
     * promoted to) one.
     */
    public function promote(PromoteWorkflowBuilderSessionRequest $request, Workspace $workspace, WorkflowBuilderSession $session)
    {
        $this->requirePermission(Permission::WorkflowBuilderUse);
        $this->ensureBelongsToWorkspace($workspace, $session);

        $workflow = $this->promoteSession->execute($session, $request->user(), $request->validated('name'));

        return ApiResponse::success([
            'workflow' => WorkflowResource::make($workflow->fresh(['nodes', 'edges'])),
        ], 'Workflow published.');
    }
}
