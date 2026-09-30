<?php

namespace App\Http\Controllers\Api\Internal\V1\Agents;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Agents\SendAgentMessageRequest;
use App\Http\Responses\AgentTurnEvents;
use App\Models\Agents\Agent;
use App\Models\Agents\AgentSession;
use App\Models\Workspaces\Workspace;
use App\Services\Agents\AgentRunner;
use Illuminate\Http\StreamedEvent;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Server-sent events for one chat turn: the same work
 * `AgentSessionController::sendMessage()` does, delivered token by token
 * instead of as one reply at the end. See `AgentTurnEvents` for the event
 * names; `done` is always sent last, whether the turn succeeded or failed.
 *
 * The request keeps running after a disconnect, so the turn still finishes
 * and is persisted even with nobody listening.
 */
class AgentSessionStreamController extends Controller
{
    public function __construct(
        private readonly AgentRunner $runner,
        private readonly AgentTurnEvents $events,
    ) {}

    public function store(SendAgentMessageRequest $request, Workspace $workspace, Agent $agent, AgentSession $session): StreamedResponse
    {
        $this->requirePermission(Permission::AgentChat);
        $this->ensureBelongsToWorkspace($workspace, $agent);
        abort_if($session->agent_id !== $agent->id, 404);

        $turn = $this->runner->stream($session, $request->validated('message'), attachments: $request->attachmentFiles());

        ignore_user_abort(true);

        return response()->eventStream(
            fn (): iterable => $this->events->stream($turn),
            endStreamWith: new StreamedEvent('done', '{}'),
        );
    }
}
