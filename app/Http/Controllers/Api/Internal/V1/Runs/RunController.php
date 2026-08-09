<?php

namespace App\Http\Controllers\Api\Internal\V1\Runs;

use App\Actions\Workflows\StartWorkflowRunAction;
use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
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
    ) {}

    public function index(Workspace $workspace)
    {
        $this->requirePermission(Permission::RunView);

        return ApiResponse::paginated(
            RunResource::collection($workspace->runs()->latest()->paginate(25)),
        );
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
}
