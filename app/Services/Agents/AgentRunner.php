<?php

namespace App\Services\Agents;

use App\Ai\Agents\EmbeddedAgent;
use App\Ai\Agents\WorkspaceAgent;
use App\Enums\Agents\AgentMessageRole;
use App\Enums\RunStatus;
use App\Events\Runs\RunCompleted;
use App\Events\Runs\RunFailed;
use App\Models\Agents\Agent as AgentModel;
use App\Models\Agents\AgentMessage;
use App\Models\Agents\AgentSession;
use App\Models\Runs\Run;
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
    ) {}

    public function run(AgentSession $session, string $message, string $triggerType = 'manual'): AgentMessage
    {
        $agent = $session->agent;

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

        try {
            $instructions = $this->skillInjector->instructionsFor($agent, $run->triggered_by);
            $response = (new WorkspaceAgent($instructions, $session, $userMessage->id, $this->tools->toolsFor($agent, $run)))
                ->prompt($message, provider: $agent->provider, model: $agent->model);

            $assistantMessage = $session->messages()->create([
                'role' => AgentMessageRole::Assistant,
                'content' => $response->text,
            ]);
            $assistantMessage->forceFill(['usage' => $response->usage->toArray()])->save();

            $run->forceFill([
                'status' => RunStatus::Completed,
                // `message_id` lets `RecordRunCreditUsage` find the exact
                // `AgentMessage` to charge for, without guessing at "the
                // latest assistant message" for this session.
                'output' => ['text' => $response->text, 'message_id' => $assistantMessage->id],
                'finished_at' => now(),
            ])->save();

            $session->forceFill(['last_activity_at' => now()])->save();

            event(new RunCompleted($run));

            return $assistantMessage;
        } catch (Throwable $e) {
            $run->forceFill([
                'status' => RunStatus::Failed,
                'error' => $e->getMessage(),
                'finished_at' => now(),
            ])->save();

            event(new RunFailed($run));

            throw $e;
        }
    }

    /**
     * A single, stateless prompt against `$agent` — what
     * `Nodes\AiAutomation\AgentNode` calls during a workflow run. `$run` is
     * the *workflow's* run (not a fresh per-turn one — an embedded call
     * doesn't create its own `Run`/`AgentSession`/history, it's scoped
     * entirely inside the calling `NodeRun`), and is the execution context
     * any attached tool call executes against, same as a chat turn's own
     * `Run` is for `NodeTool::handle()`.
     *
     * @return array{text: string, usage: array<string, mixed>}
     */
    public function ask(AgentModel $agent, Run $run, string $prompt): array
    {
        $instructions = $this->skillInjector->instructionsFor($agent, $run->triggered_by);
        $response = (new EmbeddedAgent($instructions, $this->tools->toolsFor($agent, $run)))
            ->prompt($prompt, provider: $agent->provider, model: $agent->model);

        return ['text' => $response->text, 'usage' => $response->usage->toArray()];
    }
}
