<?php

namespace App\Http\Controllers\Api\Internal\V1\Agents;

use App\Actions\Agents\CreateAgentSessionAction;
use App\Actions\Agents\SendAgentMessageAction;
use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Agents\SendAgentMessageRequest;
use App\Http\Requests\Api\Internal\V1\Agents\StoreAgentSessionRequest;
use App\Http\Resources\Api\Internal\V1\Agents\AgentMessageResource;
use App\Http\Resources\Api\Internal\V1\Agents\AgentSessionResource;
use App\Http\Responses\ApiResponse;
use App\Models\Agents\Agent;
use App\Models\Agents\AgentSession;
use App\Models\Workspaces\Workspace;

class AgentSessionController extends Controller
{
    public function __construct(
        private readonly CreateAgentSessionAction $createSession,
        private readonly SendAgentMessageAction $sendMessage,
    ) {}

    public function index(Workspace $workspace, Agent $agent)
    {
        $this->requirePermission(Permission::AgentView);
        $this->ensureBelongsToWorkspace($workspace, $agent);

        return ApiResponse::success([
            'sessions' => AgentSessionResource::collection($agent->sessions()->latest()->get()),
        ]);
    }

    public function store(StoreAgentSessionRequest $request, Workspace $workspace, Agent $agent)
    {
        $this->requirePermission(Permission::AgentChat);
        $this->ensureBelongsToWorkspace($workspace, $agent);

        $session = $this->createSession->execute($agent, $request->user(), $request->validated('title'));

        return ApiResponse::created(['session' => AgentSessionResource::make($session)], 'Session created successfully.');
    }

    public function show(Workspace $workspace, Agent $agent, AgentSession $session)
    {
        $this->requirePermission(Permission::AgentView);
        $this->ensureBelongsToWorkspace($workspace, $agent);
        abort_if($session->agent_id !== $agent->id, 404);

        return ApiResponse::success([
            'session' => AgentSessionResource::make($session->load('messages')),
        ]);
    }

    public function sendMessage(SendAgentMessageRequest $request, Workspace $workspace, Agent $agent, AgentSession $session)
    {
        $this->requirePermission(Permission::AgentChat);
        $this->ensureBelongsToWorkspace($workspace, $agent);
        abort_if($session->agent_id !== $agent->id, 404);

        $reply = $this->sendMessage->execute($session, $request->validated('message'));

        return ApiResponse::success(['message' => AgentMessageResource::make($reply)]);
    }
}
