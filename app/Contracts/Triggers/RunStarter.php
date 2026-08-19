<?php

namespace App\Contracts\Triggers;

use App\Models\Triggers\Trigger;

/**
 * The seam between the trigger system and whatever a trigger actually starts.
 *
 * `docs/TRIGGERS_PLAN.md`'s prerequisites table names this as
 * `StartWorkflowRunAction`/`AgentRunner` directly — this interface exists so
 * neither `TriggerService` nor `FireTriggerEvent` has to know which of the two
 * a given trigger points at. `App\Services\Triggers\TargetRunStarter` is the
 * implementation bound in `AppServiceProvider::register()`.
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
