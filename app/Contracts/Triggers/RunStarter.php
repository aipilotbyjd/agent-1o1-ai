<?php

namespace App\Contracts\Triggers;

use App\Models\Triggers\Trigger;

/**
 * The seam between the trigger system and whatever a trigger actually starts.
 *
 * `docs/TRIGGERS_PLAN.md`'s prerequisites table names this as
 * `StartWorkflowRunAction`/`AgentRunner` directly — this interface exists only
 * because those two don't ship until docs/WORKFLOWS_PLAN.md and
 * docs/AGENTS_PLAN.md are built. Once either lands, bind a real implementation
 * over `App\Services\Triggers\NullRunStarter` in a service provider; nothing
 * in `TriggerService`, `FireTriggerEvent`, or the HTTP/console layers changes.
 */
interface RunStarter
{
    /**
     * Whether the trigger's target can currently be run — e.g. a Workflow is
     * published and not soft-deleted, or an Agent is not soft-deleted.
     */
    public function canRun(Trigger $trigger): bool;

    /**
     * Whether the target already has a run in flight. Checked only on the
     * manual-run path — see docs/TRIGGERS_PLAN.md's design decisions table.
     */
    public function isAlreadyRunning(Trigger $trigger): bool;

    /**
     * Start the target with the given (already-filtered, already-templated)
     * payload, returning an id `TriggerEvent::markFired()` can point at.
     *
     * @param  array<string, mixed>  $payload
     */
    public function start(Trigger $trigger, array $payload): int;
}
