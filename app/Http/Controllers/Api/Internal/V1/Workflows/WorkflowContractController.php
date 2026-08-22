<?php

namespace App\Http\Controllers\Api\Internal\V1\Workflows;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use App\Services\Workflows\ContractGenerator;

/**
 * The JSON Schema of what this workflow takes in and gives back — see
 * `ContractGenerator` for how the two halves are derived, and
 * `WorkflowInterfaceController` for the human-facing form version of the
 * input half.
 */
class WorkflowContractController extends Controller
{
    public function __construct(private readonly ContractGenerator $contracts) {}

    public function show(Workspace $workspace, Workflow $workflow)
    {
        $this->requirePermission(Permission::WorkflowView);
        $this->ensureBelongsToWorkspace($workspace, $workflow);

        return ApiResponse::success(['contract' => $this->contracts->generate($workflow)]);
    }
}
