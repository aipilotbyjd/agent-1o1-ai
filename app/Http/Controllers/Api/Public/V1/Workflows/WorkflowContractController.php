<?php

namespace App\Http\Controllers\Api\Public\V1\Workflows;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Workflows\Workflow;
use App\Services\Workflows\ContractGenerator;
use Illuminate\Http\Request;

/**
 * The JSON Schema of this workflow's run `input` and `output` — what an
 * integrator needs to generate a typed client, or to assert in a contract test
 * that a workflow they depend on still returns the shape they read.
 *
 * The sibling `interface` endpoint answers the same question for a human
 * building a form; this one answers it for a program.
 */
class WorkflowContractController extends Controller
{
    public function show(Request $request, Workflow $workflow, ContractGenerator $contracts)
    {
        $this->ensureBelongsToWorkspace($this->apiKeyWorkspace($request), $workflow);

        return ApiResponse::success(['contract' => $contracts->generate($workflow)]);
    }
}
