<?php

namespace App\Http\Controllers\Api\Internal\V1\Agents;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Agents\SendAgentMessageRequest;
use App\Models\Agents\Agent;
use App\Models\Agents\AgentSession;
use App\Models\Workspaces\Workspace;
use App\Services\Agents\AgentRunner;
use App\Services\Agents\StreamedTurn;
use Illuminate\Http\StreamedEvent;
use Laravel\Ai\Streaming\Events\TextDelta;
use Laravel\Ai\Streaming\Events\ToolCall;
use Laravel\Ai\Streaming\Events\ToolResult;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Server-sent events for one chat turn: the same work
 * `AgentSessionController::sendMessage()` does, delivered token by token
 * instead of as one reply at the end.
 *
 * Event names on the wire:
 * - `delta`     — a chunk of assistant text; concatenate in order received.
 * - `tool-call` — the agent decided to call a tool (name + arguments).
 * - `tool-result` — that tool returned.
 * - `complete`  — the turn finished; carries the persisted message id, so a
 *                 client can reconcile against the REST transcript.
 * - `error`     — the turn failed; the run is marked failed before this is
 *                 sent, so no client action is needed to clean up.
 * - `done`      — always last, whether the turn succeeded or failed.
 *
 * The persisted transcript is written by `AgentRunner` regardless of whether
 * anyone is listening, so a dropped connection loses the live view, never
 * the conversation.
 */
class AgentSessionStreamController extends Controller
{
    public function __construct(private readonly AgentRunner $runner) {}

    public function store(SendAgentMessageRequest $request, Workspace $workspace, Agent $agent, AgentSession $session): StreamedResponse
    {
        $this->requirePermission(Permission::AgentChat);
        $this->ensureBelongsToWorkspace($workspace, $agent);
        abort_if($session->agent_id !== $agent->id, 404);

        $turn = $this->runner->stream($session, $request->validated('message'));

        return response()->eventStream(
            fn (): iterable => $this->events($turn),
            endStreamWith: new StreamedEvent('done', '{}'),
        );
    }

    /**
     * @return iterable<int, StreamedEvent>
     */
    private function events(StreamedTurn $turn): iterable
    {
        try {
            foreach ($turn->response as $event) {
                $streamed = match (true) {
                    $event instanceof TextDelta => new StreamedEvent('delta', ['delta' => $event->delta]),
                    $event instanceof ToolCall => new StreamedEvent('tool-call', [
                        'id' => $event->toolCall->id,
                        'name' => $event->toolCall->name,
                        'arguments' => $event->toolCall->arguments,
                    ]),
                    $event instanceof ToolResult => new StreamedEvent('tool-result', [
                        'id' => $event->toolResult->id,
                        'name' => $event->toolResult->name,
                    ]),
                    default => null,
                };

                if ($streamed !== null) {
                    yield $streamed;
                }
            }

            // `AgentRunner::stream()` registered the `then()` callback that
            // persists the reply, and iteration above has now run it — so the
            // run's output holds the message id by this point.
            $run = $turn->run->fresh();

            yield new StreamedEvent('complete', [
                'run_id' => $run->id,
                'status' => $run->status->value,
                'message_id' => $run->output['message_id'] ?? null,
                'text' => $run->output['text'] ?? null,
            ]);
        } catch (Throwable $e) {
            // Nothing else will: the failure happens inside this generator,
            // not inside AgentRunner. See `AgentRunner::stream()`.
            $this->runner->failTurn($turn->run, $e);

            yield new StreamedEvent('error', ['message' => $e->getMessage()]);
        }
    }
}
