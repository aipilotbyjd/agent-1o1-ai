<?php

namespace App\Contracts\Triggers;

use App\Models\Triggers\Trigger;

/**
 * Starts whatever a trigger points at, a workflow or an agent.
 *
 * The trigger pipeline (TriggerService, FireTriggerEvent) talks only to this
 * interface, so it never needs to know which kind of target it has.
 * TargetRunStarter is the implementation, bound in AppServiceProvider.
 */
interface RunStarter
{
    /**
     * Whether the target can run right now: a workflow that is published and
     * not deleted, or an agent that is not deleted.
     */
    public function canRun(Trigger $trigger): bool;

    /**
     * Whether the target already has a run in flight.
     *
     * Asked by "Run now" (to answer "busy") and by triggers set to skip
     * events while the last run is still going.
     */
    public function isAlreadyRunning(Trigger $trigger): bool;

    /**
     * Start the target with an event's payload and return the new run's id.
     *
     * @param  array<string, mixed>  $payload
     */
    public function start(Trigger $trigger, array $payload): string;
}
