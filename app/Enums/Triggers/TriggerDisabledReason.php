<?php

namespace App\Enums\Triggers;

/**
 * Why the circuit breaker switched a trigger off.
 *
 * @see Trigger::recordFailure()
 * @see Trigger::recordSkip()
 */
enum TriggerDisabledReason: string
{
    /**
     * Too many events in a row failed to start a run.
     */
    case Failures = 'failures';

    /**
     * Too many events in a row were dropped without running: out of credits,
     * or the last run was still going under the "skip" overlap policy.
     */
    case Skips = 'skips';
}
