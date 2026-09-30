<?php

namespace App\Http\Responses;

use App\Ai\Tools\InvokeAgentTool;
use App\Http\Resources\Api\Internal\V1\Agents\AgentActionResource;
use App\Http\Resources\Api\Internal\V1\Agents\AgentMessageResource;
use App\Models\Agents\AgentAction;
use App\Services\Agents\AgentRunner;
use App\Services\Agents\StreamedTurn;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\StreamedEvent;
use Illuminate\Support\Str;
use Laravel\Ai\Approvals\PendingApproval;
use Laravel\Ai\Streaming\Events\TextDelta;
use Laravel\Ai\Streaming\Events\ToolApprovalRequest;
use Laravel\Ai\Streaming\Events\ToolCall;
use Laravel\Ai\Streaming\Events\ToolResult;
use Throwable;

/**
 * Server-sent events for one agent turn — a new message, or a paused turn
 * resumed after approvals. Event names on the wire:
 *
 * - `delta`     — a chunk of assistant text; concatenate in order received.
 * - `tool-call` — the agent decided to call a tool (name + arguments).
 * - `tool-result` — that tool returned, with its output and whether it succeeded.
 * - `approval-required` — the turn paused: these actions wait for a decision.
 * - `complete`  — the turn settled; carries the persisted message id and the
 *                 run's status (`completed`, or `awaiting_approval` when it paused).
 * - `error`     — the turn failed; the run is marked failed before this is
 *                 sent, so no client action is needed to clean up.
 *
 * The persisted transcript is written by `AgentRunner` regardless of whether
 * anyone is listening: a dropped connection loses the live view, never the
 * conversation — the rest of the stream is drained server-side.
 */
class AgentTurnEvents
{
    public function __construct(private readonly AgentRunner $runner) {}

    /**
     * `eventStream()` stops pulling from this generator once the client
     * disconnects; the `finally` below then runs as the generator is
     * discarded and finishes the turn without a listener.
     *
     * @return iterable<int, StreamedEvent>
     */
    public function stream(StreamedTurn $turn): iterable
    {
        $events = $turn->response->getIterator();
        $settled = false;

        try {
            foreach ($events as $event) {
                $streamed = match (true) {
                    $event instanceof TextDelta => new StreamedEvent('delta', ['delta' => $event->delta]),
                    $event instanceof ToolCall => new StreamedEvent('tool-call', [
                        'id' => $event->toolCall->id,
                        'name' => $event->toolCall->name,
                        'arguments' => $event->toolCall->arguments,
                    ]),
                    $event instanceof ToolResult => new StreamedEvent('tool-result', array_filter([
                        'id' => $event->toolResult->id,
                        'name' => $event->toolResult->name,
                        // A started subagent's task id, so the chat can track it live.
                        'result' => $event->toolResult->name === InvokeAgentTool::NAME
                            ? json_decode((string) $event->toolResult->result, true)
                            : null,
                        'output' => Str::limit((string) ($event->error ?? $event->toolResult->result), AgentMessageResource::TOOL_OUTPUT_LIMIT),
                        'successful' => $event->successful,
                        'denied' => $event->denied ?: null,
                    ], fn ($value) => $value !== null)),
                    $event instanceof ToolApprovalRequest => new StreamedEvent('approval-required', [
                        'actions' => AgentActionResource::collection($this->actionsFor($turn, $event))->resolve(),
                    ]),
                    default => null,
                };

                if ($streamed !== null) {
                    yield $streamed;
                }
            }

            $settled = true;

            // The `then()` callback that persists the reply has run by now,
            // so the run's output holds the message id.
            $run = $turn->run->fresh();

            yield new StreamedEvent('complete', array_filter([
                'run_id' => $run->id,
                'status' => $run->status->value,
                'message_id' => $run->output['message_id'] ?? null,
                'text' => $run->output['text'] ?? null,
                'pending_action_ids' => $run->output['pending_action_ids'] ?? null,
            ], fn ($value) => $value !== null));
        } catch (Throwable $e) {
            $settled = true;

            // Nothing else will: the failure happens inside this generator,
            // not inside AgentRunner. See `AgentRunner::stream()`.
            $this->runner->failTurn($turn->run, $e);

            yield new StreamedEvent('error', ['message' => $e->getMessage()]);
        } finally {
            if (! $settled) {
                $this->runner->drain($turn, $events);
            }
        }
    }

    /**
     * The recorded actions behind a pause — the SDK only knows the tool
     * call ids; the gate recorded each as an `AgentAction` when it asked.
     *
     * @return Collection<int, AgentAction>
     */
    private function actionsFor(StreamedTurn $turn, ToolApprovalRequest $event)
    {
        return AgentAction::query()
            ->where('run_id', $turn->run->id)
            ->whereIn('tool_call_id', $event->pendingApprovals->map(fn (PendingApproval $approval): string => $approval->id)->all())
            ->get();
    }
}
