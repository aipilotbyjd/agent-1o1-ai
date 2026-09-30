<?php

namespace App\Http\Controllers\Api\Internal\V1\Agents;

use App\Actions\Agents\ResolveAgentActionsAction;
use App\Enums\RunStatus;
use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Agents\DecideAgentActionsRequest;
use App\Http\Resources\Api\Internal\V1\Agents\AgentActionResource;
use App\Http\Responses\AgentTurnEvents;
use App\Http\Responses\ApiResponse;
use App\Models\Agents\Agent;
use App\Models\Agents\AgentAction;
use App\Models\Agents\AgentSession;
use App\Models\Workspaces\Workspace;
use App\Services\Agents\AgentRunner;
use App\Services\Agents\Approvals\ActionResumer;
use Illuminate\Http\StreamedEvent;

/**
 * Deciding from an open chat: records the decisions, and if that leaves the
 * conversation's paused turn fully decided, continues it right here as
 * server-sent events (same events as sending a message — see
 * `AgentTurnEvents`), so the person watches the agent carry on.
 *
 * Anything else the decisions unblocked — a subagent's turn, say — resumes
 * on the queue as usual. When the turn still has undecided actions, the
 * response is plain JSON listing what was decided.
 */
class AgentActionStreamController extends Controller
{
    public function __construct(
        private readonly ResolveAgentActionsAction $resolve,
        private readonly ActionResumer $resumer,
        private readonly AgentRunner $runner,
        private readonly AgentTurnEvents $events,
    ) {}

    public function store(DecideAgentActionsRequest $request, Workspace $workspace, Agent $agent, AgentSession $session)
    {
        $this->requirePermission(Permission::AgentChat);
        $this->ensureBelongsToWorkspace($workspace, $agent);
        abort_if($session->agent_id !== $agent->id, 404);

        $decided = $this->resolve->execute($request->user(), $request->decisions(), resume: false);

        $run = $session->runs()->where('status', RunStatus::AwaitingApproval)->latest('id')->first();
        $resumesHere = $run !== null && $this->resumer->runIsReady($run);

        $this->resumer->resumeReady($decided->reject(fn (AgentAction $action): bool => $resumesHere && $action->run_id === $run->id));

        if (! $resumesHere) {
            return ApiResponse::success(['actions' => AgentActionResource::collection($decided), 'resumed' => false]);
        }

        $turn = $this->runner->resumeStream($run);

        ignore_user_abort(true);

        return response()->eventStream(
            fn (): iterable => $this->events->stream($turn),
            endStreamWith: new StreamedEvent('done', '{}'),
        );
    }
}
