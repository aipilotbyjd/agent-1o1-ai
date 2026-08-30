<?php

namespace App\Services\Agents;

use App\Actions\Agents\CreateAgentSessionAction;
use App\Ai\Agents\EmbeddedAgent;
use App\Ai\Agents\WorkspaceAgent;
use App\Enums\Agents\AgentMessageRole;
use App\Enums\RunStatus;
use App\Events\Runs\RunCompleted;
use App\Events\Runs\RunFailed;
use App\Models\Agents\Agent as AgentModel;
use App\Models\Agents\AgentMessage;
use App\Models\Agents\AgentSession;
use App\Models\Artifacts\Artifact;
use App\Models\Runs\Run;
use App\Services\Ai\ModelCatalogResolver;
use App\Services\Billing\CreditGate;
use InvalidArgumentException;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\StreamedAgentResponse;
use Throwable;

/**
 * The Agent layer's engine entry point — mirrors `WorkflowRunner`'s role for
 * the Workflow engine.
 *
 * One `Run` per turn (`runnable_type = AgentSession::class`) — a session's
 * `runs()` relation lists its full turn history, the same way a workflow's
 * `Run` list works. See docs/WORKFLOWS_PLAN.md's `runs` table note on why
 * agent invocations and workflow executions share one table. This turn's own
 * `Run` doubles as the execution context `NodeTool::handle()` passes to
 * `NodeContract::execute()` for any tool call made during it.
 */
class AgentRunner
{
    public function __construct(
        private readonly ToolRegistry $tools,
        private readonly SkillInjector $skillInjector,
        private readonly CreditGate $creditGate,
        private readonly ModelCatalogResolver $modelCatalog,
        private readonly CreateAgentSessionAction $createSession,
    ) {}

    public function run(AgentSession $session, string $message, string $triggerType = 'manual'): AgentMessage
    {
        $turn = $this->openTurn($session, $message, $triggerType);

        try {
            $response = $turn->agent->prompt($message, provider: $turn->provider, model: $turn->model);

            return $this->completeTurn($turn, $response->text, $this->usageFor($turn, $response));
        } catch (Throwable $e) {
            $this->failTurn($turn->run, $e);

            throw $e;
        }
    }

    /**
     * The same turn, delivered incrementally. The caller iterates the
     * returned `StreamableAgentResponse` (an SSE controller does; see
     * `AgentSessionStreamController`) and the turn is closed out by the
     * `then()` callback registered here once the provider finishes.
     *
     * The caller owns failure: nothing runs until the stream is iterated, so
     * an exception surfaces *there*, not here, and whoever iterates must call
     * `failTurn()` — otherwise the turn's `Run` would sit in `running`
     * forever.
     */
    public function stream(AgentSession $session, string $message, string $triggerType = 'manual'): StreamedTurn
    {
        $turn = $this->openTurn($session, $message, $triggerType);

        $response = $turn->agent->stream($message, provider: $turn->provider, model: $turn->model);

        $response->then(function (StreamedAgentResponse $streamed) use ($turn): void {
            $this->completeTurn($turn, $streamed->text, $this->usageFor($turn, $streamed));
        });

        return new StreamedTurn($turn->run, $response);
    }

    /**
     * Everything that happens before the provider is called: credit gate,
     * the turn's own `Run`, the user's message, and the SDK agent built from
     * the version this conversation is pinned to.
     */
    private function openTurn(AgentSession $session, string $message, string $triggerType): AgentTurn
    {
        // The version the conversation was started against, not whatever the
        // agent looks like right now — see `AgentSession::pinnedAgent()`.
        $agent = $session->pinnedAgent();

        // Before the turn's `Run` exists — a workspace out of credits is
        // refused up front rather than after the model call is paid for.
        $this->creditGate->assertCanStartRun($session->workspace);

        $run = $session->runs()->create([
            'workspace_id' => $session->workspace_id,
            'trigger_type' => $triggerType,
            'input' => ['message' => $message],
        ]);

        $run->forceFill(['status' => RunStatus::Running, 'started_at' => now()])->save();

        $userMessage = $session->messages()->create([
            'role' => AgentMessageRole::User,
            'content' => $message,
        ]);

        $instructions = $this->skillInjector->instructionsFor($agent, $run->triggered_by);
        [$provider, $model] = $this->resolveProvider($agent);

        return new AgentTurn(
            $session,
            $run,
            new WorkspaceAgent($instructions, $session, $userMessage->id, $this->tools->toolsFor($agent, $run)),
            $provider,
            $model,
        );
    }

    /**
     * The provider/model to prompt with for `$agent` — either its plain
     * `provider`/`model` columns, or, when it's opted into a
     * `model_catalog_id`, the ordered failover chain resolved from it (see
     * `ModelCatalogResolver`). A resolved chain already carries its own
     * model ids, so `model` comes back `null` in that case.
     *
     * @return array{0: string|array<string, string>, 1: ?string}
     */
    private function resolveProvider(AgentModel $agent): array
    {
        if ($agent->model_catalog_id === null) {
            return [$agent->provider, $agent->model];
        }

        return [$this->modelCatalog->providerChain($agent->modelCatalog->slug), null];
    }

    /**
     * `$response->usage` plus what `CreditMeter` needs to price this turn
     * like a Gumloop chat: which model actually served it (`Meta`, so real
     * $-based pricing can look it up), how many tool calls it made (1
     * credit each), and how long it ran (5 credits/session-minute compute).
     * `Usage`/`Meta`/`toolCalls` are all populated by the SDK already —
     * this just persists what the app previously discarded.
     *
     * @return array<string, mixed>
     */
    private function usageFor(AgentTurn $turn, AgentResponse $response): array
    {
        return [
            ...$response->usage->toArray(),
            ...$response->meta->toArray(),
            'tool_call_count' => $response->toolCalls->count(),
            'duration_seconds' => $turn->run->started_at->diffInSeconds(now()),
        ];
    }

    /**
     * @param  array<string, mixed>  $usage
     */
    private function completeTurn(AgentTurn $turn, string $text, array $usage): AgentMessage
    {
        $assistantMessage = $turn->session->messages()->create([
            'role' => AgentMessageRole::Assistant,
            'content' => $text,
        ]);
        $assistantMessage->forceFill(['usage' => $usage])->save();

        $turn->run->forceFill([
            'status' => RunStatus::Completed,
            // `message_id` lets `RecordRunCreditUsage` find the exact
            // `AgentMessage` to charge for, without guessing at "the
            // latest assistant message" for this session.
            'output' => ['text' => $text, 'message_id' => $assistantMessage->id],
            'finished_at' => now(),
        ])->save();

        $turn->session->forceFill(['last_activity_at' => now()])->save();

        event(new RunCompleted($turn->run));

        return $assistantMessage;
    }

    /**
     * Marks a turn's `Run` failed. Public because a streamed turn fails in
     * the caller's loop rather than inside this class — see `stream()`.
     * Idempotent, so a caller that fails a turn the SDK already closed out
     * can't overwrite a completed run.
     */
    public function failTurn(Run $run, Throwable $e): void
    {
        if ($run->fresh()?->status->isTerminal()) {
            return;
        }

        $run->forceFill([
            'status' => RunStatus::Failed,
            'error' => $e->getMessage(),
            'finished_at' => now(),
        ])->save();

        event(new RunFailed($run));
    }

    /**
     * A single, stateless prompt against `$agent` with no history and no
     * conversation of its own — what `EvalRunner` calls for a graded eval
     * case, where each case is meant to be a fresh, independent turn.
     * `$run` is the *calling* run (an eval run's own `Run`, not a fresh
     * per-turn one), and is the execution context any attached tool call
     * executes against, same as a chat turn's own `Run` is for
     * `NodeTool::handle()`.
     *
     * @return array{text: string, usage: array<string, mixed>}
     */
    public function ask(AgentModel $agent, Run $run, string $prompt): array
    {
        $instructions = $this->skillInjector->instructionsFor($agent, $run->triggered_by);
        [$provider, $model] = $this->resolveProvider($agent);

        $startedAt = now();

        $response = (new EmbeddedAgent($instructions, $this->tools->toolsFor($agent, $run)))
            ->prompt($prompt, provider: $provider, model: $model);

        $usage = [
            ...$response->usage->toArray(),
            ...$response->meta->toArray(),
            'tool_call_count' => $response->toolCalls->count(),
            'duration_seconds' => $startedAt->diffInSeconds(now()),
        ];

        return ['text' => $response->text, 'usage' => $usage];
    }

    /**
     * What `Nodes\AiAutomation\AgentNode` calls during a workflow run — see
     * docs/gumloop/output/raw/core-concepts/agent_node.md's "Node Inputs"/
     * "Node Outputs"/"Continuing Conversations" sections. Unlike `ask()`,
     * this turn is persisted as a real `AgentSession`/`AgentMessage` pair so
     * a later Agent-node call can continue it via `$previousConversationId`
     * — started fresh when that's null, otherwise loaded and validated
     * against `$agent` and `$run`'s workspace.
     *
     * Deliberately creates no `Run` of its own and never fires
     * `RunCompleted`: this call's tokens are already billed as part of the
     * calling workflow node's own `NodeRun.usage` (`CreditMeter::costForNodeRun`)
     * — billing them again under `AgentStep` would double-charge the same
     * tokens. `AgentMessage.usage` is still recorded, purely so the turn's
     * cost is visible when inspecting the conversation later.
     *
     * @return array{
     *     text: string,
     *     usage: array<string, mixed>,
     *     conversation_id: int,
     *     messages: array<int, array{role: string, content: string, tool_calls: array<int, mixed>|null}>,
     *     attachment_names: string,
     * }
     */
    public function askInConversation(AgentModel $agent, Run $run, string $prompt, ?int $previousConversationId): array
    {
        $session = $previousConversationId === null
            ? $this->createSession->execute($agent, $run->triggeredBy)
            : $this->conversationFor($agent, $run, $previousConversationId);

        $instructions = $this->skillInjector->instructionsFor($agent, $run->triggered_by);
        [$provider, $model] = $this->resolveProvider($agent);

        $userMessage = $session->messages()->create([
            'role' => AgentMessageRole::User,
            'content' => $prompt,
        ]);

        $startedAt = now();

        $response = (new WorkspaceAgent($instructions, $session, $userMessage->id, $this->tools->toolsFor($agent, $run, $session)))
            ->prompt($prompt, provider: $provider, model: $model);

        $usage = [
            ...$response->usage->toArray(),
            ...$response->meta->toArray(),
            'tool_call_count' => $response->toolCalls->count(),
            'duration_seconds' => $startedAt->diffInSeconds(now()),
        ];

        $assistantMessage = $session->messages()->create([
            'role' => AgentMessageRole::Assistant,
            'content' => $response->text,
            'tool_calls' => $response->toolCalls->isNotEmpty() ? $response->toolCalls->toArray() : null,
        ]);
        $assistantMessage->forceFill(['usage' => $usage])->save();

        $session->forceFill(['last_activity_at' => now()])->save();

        return [
            'text' => $response->text,
            'usage' => $usage,
            'conversation_id' => $session->id,
            'messages' => $session->messages()->oldest()->get()
                ->map(fn (AgentMessage $message): array => [
                    'role' => $message->role->value,
                    'content' => $message->content,
                    'tool_calls' => $message->tool_calls,
                ])
                ->all(),
            'attachment_names' => Artifact::query()
                ->where('run_id', $run->id)
                ->where('agent_session_id', $session->id)
                ->pluck('filename')
                ->implode(','),
        ];
    }

    /**
     * Loads and validates a conversation to continue — it must exist, in
     * the calling run's own workspace, for this same `$agent`. A session
     * belongs to exactly one agent (`AgentSession::pinnedAgent()`), so
     * continuing it with a different agent selected on the node wouldn't
     * mean anything; Gumloop's own doc calls the equivalent failure
     * "Conversation Not Found".
     */
    private function conversationFor(AgentModel $agent, Run $run, int $previousConversationId): AgentSession
    {
        $session = AgentSession::query()
            ->where('workspace_id', $run->workspace_id)
            ->where('agent_id', $agent->id)
            ->find($previousConversationId);

        if ($session === null) {
            throw new InvalidArgumentException("Conversation [{$previousConversationId}] not found for agent [{$agent->id}] in this workspace.");
        }

        return $session;
    }
}
