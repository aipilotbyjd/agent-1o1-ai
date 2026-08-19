<?php

namespace App\Http\Controllers\Api\Internal\V1\Agents;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Agents\StoreAgentKnowledgeRequest;
use App\Http\Requests\Api\Internal\V1\Agents\UpdateAgentKnowledgeRequest;
use App\Http\Resources\Api\Internal\V1\Agents\AgentKnowledgeResource;
use App\Http\Responses\ApiResponse;
use App\Models\Agents\Agent;
use App\Models\Agents\AgentKnowledge;
use App\Models\Workspaces\Workspace;

/**
 * The "always know this" half of docs/AGENTS_PLAN.md's Knowledge/RAG split:
 * entries here are injected straight into the agent's system prompt by
 * `SkillInjector`, per agent. The "look this up when relevant" half is the
 * workspace knowledge base (`KnowledgeBaseController`).
 */
class AgentKnowledgeController extends Controller
{
    public function index(Workspace $workspace, Agent $agent)
    {
        $this->requirePermission(Permission::AgentView);
        $this->ensureBelongsToWorkspace($workspace, $agent);

        $entries = $agent->knowledge()->orderBy('sort_order')->orderBy('id')->get();

        return ApiResponse::success(['knowledge' => AgentKnowledgeResource::collection($entries)]);
    }

    public function store(StoreAgentKnowledgeRequest $request, Workspace $workspace, Agent $agent)
    {
        $this->requirePermission(Permission::AgentManage);
        $this->ensureBelongsToWorkspace($workspace, $agent);

        $entry = $agent->knowledge()->create([
            ...$request->validated(),
            'tokens' => AgentKnowledge::estimateTokens($request->validated('content')),
        ]);

        return ApiResponse::created(['knowledge' => AgentKnowledgeResource::make($entry)], 'Knowledge entry created.');
    }

    public function update(UpdateAgentKnowledgeRequest $request, Workspace $workspace, Agent $agent, AgentKnowledge $knowledge)
    {
        $this->requirePermission(Permission::AgentManage);
        $this->ensureBelongsToWorkspace($workspace, $agent);
        abort_if($knowledge->agent_id !== $agent->id, 404);

        $entry = $request->validated();

        if (array_key_exists('content', $entry)) {
            $entry['tokens'] = AgentKnowledge::estimateTokens($entry['content']);
        }

        $knowledge->update($entry);

        return ApiResponse::success(['knowledge' => AgentKnowledgeResource::make($knowledge)], 'Knowledge entry updated.');
    }

    public function destroy(Workspace $workspace, Agent $agent, AgentKnowledge $knowledge)
    {
        $this->requirePermission(Permission::AgentManage);
        $this->ensureBelongsToWorkspace($workspace, $agent);
        abort_if($knowledge->agent_id !== $agent->id, 404);

        $knowledge->delete();

        return ApiResponse::noContent();
    }
}
