<?php

namespace App\Http\Controllers\Api\Public\V1\Runs;

use App\Actions\Workflows\StartWorkflowRunAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Public\V1\StartRunRequest;
use App\Http\Resources\Api\Public\V1\RunResource;
use App\Http\Responses\ApiResponse;
use App\Models\Runs\Run;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use Illuminate\Http\Request;

class RunController extends Controller
{
    public function __construct(
        private readonly StartWorkflowRunAction $startWorkflowRun,
    ) {}

    public function store(StartRunRequest $request, Workflow $workflow)
    {
        /** @var Workspace $workspace */
        $workspace = $request->attributes->get('workspace');

        $this->ensureBelongsToWorkspace($workspace, $workflow);

        $run = $this->startWorkflowRun->execute($workflow, $request->validated('input') ?? [], triggerType: 'api');

        return ApiResponse::success(['run' => RunResource::make($run)], 'Run started.', 202);
    }

    public function show(Request $request, Run $run)
    {
        /** @var Workspace $workspace */
        $workspace = $request->attributes->get('workspace');

        $this->ensureBelongsToWorkspace($workspace, $run);

        return ApiResponse::success(['run' => RunResource::make($run)]);
    }
}
