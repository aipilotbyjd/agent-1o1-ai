<?php

namespace App\Http\Controllers\Api\Internal\V1\Runs;

use App\Actions\Workflows\CancelRunAction;
use App\Actions\Workflows\RetryRunAction;
use App\Actions\Workflows\StartWorkflowRunAction;
use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Runs\IndexRunsRequest;
use App\Http\Requests\Api\Internal\V1\Runs\RetryRunRequest;
use App\Http\Requests\Api\Internal\V1\Runs\StartRunRequest;
use App\Http\Resources\Api\Internal\V1\Runs\RunResource;
use App\Http\Responses\ApiResponse;
use App\Models\Runs\Run;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;

class RunController extends Controller
{
    public function __construct(
        private readonly StartWorkflowRunAction $startWorkflowRun,
        private readonly CancelRunAction $cancelRun,
        private readonly RetryRunAction $retryRun,
    ) {}

    public function index(IndexRunsRequest $request, Workspace $workspace)
    {
        $this->requirePermission(Permission::RunView);

        $runs = $workspace->runs()
            ->when($request->validated('status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->validated('workflow_id'), fn ($query, $workflowId) => $query->where('workflow_id', $workflowId))
            ->when($request->validated('trigger_type'), fn ($query, $type) => $query->where('trigger_type', $type))
            ->when($request->validated('exclude_trigger_type'), fn ($query, $type) => $query->where('trigger_type', '!=', $type))
            ->latest()
            ->paginate($request->validated('per_page') ?? 25)
            ->withQueryString();

        return ApiResponse::paginated(RunResource::collection($runs));
    }

    public function show(Workspace $workspace, Run $run)
    {
        $this->requirePermission(Permission::RunView);
        $this->ensureBelongsToWorkspace($workspace, $run);

        return ApiResponse::success(['run' => RunResource::make($run->load('nodeRuns'))]);
    }

    public function store(StartRunRequest $request, Workspace $workspace, Workflow $workflow)
    {
        $this->requirePermission(Permission::RunTrigger);
        $this->ensureBelongsToWorkspace($workspace, $workflow);

        $run = $this->startWorkflowRun->execute(
            $workflow,
            $request->validated('input') ?? [],
            $request->user(),
        );

        return ApiResponse::success(['run' => RunResource::make($run)], 'Run started.', 202);
    }

    /**
     * Cancelling any run in a tree cancels the whole tree — see
     * `CancelRunAction`. Gated on `RunTrigger` rather than `RunView`: stopping
     * work is the same class of act as starting it.
     */
    public function cancel(Workspace $workspace, Run $run)
    {
        $this->requirePermission(Permission::RunTrigger);
        $this->ensureBelongsToWorkspace($workspace, $run);

        $cancelled = $this->cancelRun->execute($run, request()->user());

        return ApiResponse::success(['run' => RunResource::make($cancelled->load('nodeRuns'))], 'Run cancelled.');
    }

    public function retry(RetryRunRequest $request, Workspace $workspace, Run $run)
    {
        $this->requirePermission(Permission::RunTrigger);
        $this->ensureBelongsToWorkspace($workspace, $run);

        $retry = $this->retryRun->execute(
            $run,
            $request->user(),
            $request->validated('from_node_key'),
        );

        return ApiResponse::success(['run' => RunResource::make($retry->load('nodeRuns'))], 'Run retried.', 202);
    }
}
