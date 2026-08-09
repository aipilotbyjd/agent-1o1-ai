<?php

namespace App\Http\Controllers\Api\Internal\V1\Agents;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Agents\StoreAgentRequest;
use App\Http\Requests\Api\Internal\V1\Agents\UpdateAgentRequest;
use App\Http\Resources\Api\Internal\V1\Agents\AgentResource;
use App\Http\Responses\ApiResponse;
use App\Models\Agents\Agent;
use App\Models\Workspaces\Workspace;
use Illuminate\Support\Str;

class AgentController extends Controller
{
    public function index(Workspace $workspace)
    {
        $this->requirePermission(Permission::AgentView);

        return ApiResponse::success([
            'agents' => AgentResource::collection($workspace->agents()->latest()->get()),
        ]);
    }

    public function store(StoreAgentRequest $request, Workspace $workspace)
    {
        $this->requirePermission(Permission::AgentManage);

        $agent = $workspace->agents()->create([
            ...$request->validated(),
            'slug' => $request->validated('slug') ?: Str::slug($request->validated('name')).'-'.Str::random(6),
            'created_by' => $request->user()->id,
        ]);

        return ApiResponse::created(['agent' => AgentResource::make($agent)], 'Agent created successfully.');
    }

    public function show(Workspace $workspace, Agent $agent)
    {
        $this->requirePermission(Permission::AgentView);
        $this->ensureBelongsToWorkspace($workspace, $agent);

        return ApiResponse::success(['agent' => AgentResource::make($agent)]);
    }

    public function update(UpdateAgentRequest $request, Workspace $workspace, Agent $agent)
    {
        $this->requirePermission(Permission::AgentManage);
        $this->ensureBelongsToWorkspace($workspace, $agent);

        $agent->update($request->validated());

        return ApiResponse::success(['agent' => AgentResource::make($agent)], 'Agent updated successfully.');
    }

    public function destroy(Workspace $workspace, Agent $agent)
    {
        $this->requirePermission(Permission::AgentManage);
        $this->ensureBelongsToWorkspace($workspace, $agent);

        $agent->delete();

        return ApiResponse::noContent();
    }
}
