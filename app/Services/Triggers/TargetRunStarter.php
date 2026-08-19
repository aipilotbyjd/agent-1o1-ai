<?php

namespace App\Services\Triggers;

use App\Actions\Agents\CreateAgentSessionAction;
use App\Actions\Agents\SendAgentMessageAction;
use App\Actions\Workflows\StartWorkflowRunAction;
use App\Contracts\Triggers\RunStarter;
use App\Enums\RunStatus;
use App\Models\Agents\Agent;
use App\Models\Agents\AgentSession;
use App\Models\Runs\Run;
use App\Models\Triggers\Trigger;
use App\Models\Workflows\Workflow;
use App\Services\Workflows\TemplateResolver;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * The real `RunStarter` docs/TRIGGERS_PLAN.md's prerequisites table names:
 * a trigger's target is either a `Workflow` (started through
 * `StartWorkflowRunAction`, exactly as the API surfaces do) or an `Agent`
 * (a fresh `AgentSession` plus one message, through `AgentRunner`). Both
 * paths end in a `runs` row, which is what `TriggerEvent::markFired()`
 * points at.
 *
 * Every method resolves the target through `targetFor()` rather than
 * `$trigger->target` directly, so a soft-deleted, missing, or
 * cross-workspace target is uniformly "not runnable" instead of each method
 * hand-rolling that check.
 */
class TargetRunStarter implements RunStarter
{
    public function __construct(
        private readonly StartWorkflowRunAction $startWorkflowRun,
        private readonly CreateAgentSessionAction $createAgentSession,
        private readonly SendAgentMessageAction $sendAgentMessage,
        private readonly TemplateResolver $templates,
    ) {}

    public function canRun(Trigger $trigger): bool
    {
        $target = $this->targetFor($trigger);

        return match (true) {
            // An unpublished workflow has no `current_version_id` to pin a run
            // to — StartWorkflowRunAction would throw, so refuse here instead
            // and let intake record a Skipped event.
            $target instanceof Workflow => $target->isPublished(),
            $target instanceof Agent => true,
            default => false,
        };
    }

    public function isAlreadyRunning(Trigger $trigger): bool
    {
        $target = $this->targetFor($trigger);

        $runs = match (true) {
            $target instanceof Workflow => Run::query()->where('workflow_id', $target->id),
            $target instanceof Agent => Run::query()
                ->where('runnable_type', (new AgentSession)->getMorphClass())
                ->whereIn('runnable_id', $target->sessions()->select('id')),
            default => null,
        };

        return $runs?->whereIn('status', RunStatus::inFlight())->exists() ?? false;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function start(Trigger $trigger, array $payload): int
    {
        $target = $this->targetFor($trigger);

        return match (true) {
            $target instanceof Workflow => $this->startWorkflow($trigger, $target, $payload),
            $target instanceof Agent => $this->startAgent($trigger, $target, $payload),
            // Reached only if the target was deleted between intake's canRun()
            // check and this queued firing. Throwing (rather than returning a
            // sentinel id) hands it to FireTriggerEvent's retry/circuit-breaker
            // handling, which is where every other firing failure is decided.
            default => throw new RuntimeException("Trigger {$trigger->id} has no runnable target."),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function startWorkflow(Trigger $trigger, Workflow $workflow, array $payload): int
    {
        return $this->startWorkflowRun->execute(
            $workflow,
            $payload,
            // The trigger's creator is the closest thing an automated firing
            // has to an actor — it's what run history and per-user skill
            // injection attribute the run to.
            $trigger->creator,
            $trigger->type->value,
        )->id;
    }

    /**
     * A trigger-fired agent turn gets its own session so one event's
     * conversation can never bleed into the next one's context.
     *
     * @param  array<string, mixed>  $payload
     */
    private function startAgent(Trigger $trigger, Agent $agent, array $payload): int
    {
        $session = $this->createAgentSession->execute(
            $agent,
            $trigger->creator,
            "{$trigger->type->value} trigger #{$trigger->id}",
        );

        $this->sendAgentMessage->execute($session, $this->messageFor($trigger, $payload), $trigger->type->value);

        // AgentRunner creates one `runs` row per turn, and this session was
        // created a line ago with exactly one turn on it.
        return $session->runs()->latest('id')->value('id')
            ?? throw new RuntimeException("Agent session {$session->id} produced no run.");
    }

    /**
     * The prompt the agent is sent. A trigger can template one from the
     * payload (`config.message`, e.g. `"Triage: {{ payload.issue.title }}"`);
     * without one the raw payload is handed over as JSON for the agent's own
     * instructions to interpret.
     *
     * @param  array<string, mixed>  $payload
     */
    private function messageFor(Trigger $trigger, array $payload): string
    {
        $message = $this->templates->resolve(
            $trigger->config['message'] ?? null,
            ['payload' => $payload, 'trigger' => ['id' => $trigger->id, 'type' => $trigger->type->value]],
        );

        if (is_string($message) && trim($message) !== '') {
            return $message;
        }

        return $payload === []
            ? "The {$trigger->type->value} trigger fired with no payload."
            : json_encode($payload, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    }

    /**
     * The trigger's target, or null when it can't be run at all: deleted
     * (morphTo excludes soft-deleted rows), never existed, or — the one that
     * matters for tenancy — belongs to a different workspace than the trigger
     * itself.
     */
    private function targetFor(Trigger $trigger): ?Model
    {
        $target = $trigger->target;

        if ($target === null || $target->workspace_id !== $trigger->workspace_id) {
            return null;
        }

        return $target;
    }
}
