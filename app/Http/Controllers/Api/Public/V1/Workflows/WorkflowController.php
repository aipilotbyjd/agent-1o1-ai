<?php

namespace App\Http\Controllers\Api\Public\V1\Workflows;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Public\V1\WorkflowResource;
use App\Http\Responses\ApiResponse;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use Illuminate\Http\Request;

class WorkflowController extends Controller
{
    public function index(Request $request)
    {
        /** @var Workspace $workspace */
        $workspace = $request->attributes->get('workspace');

        return ApiResponse::success([
            'workflows' => WorkflowResource::collection($workspace->workflows()->latest()->get()),
        ]);
    }

    public function show(Request $request, Workflow $workflow)
    {
        /** @var Workspace $workspace */
        $workspace = $request->attributes->get('workspace');

        $this->ensureBelongsToWorkspace($workspace, $workflow);

        return ApiResponse::success(['workflow' => WorkflowResource::make($workflow)]);
    }
}
