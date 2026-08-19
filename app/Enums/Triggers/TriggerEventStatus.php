<?php

namespace App\Enums\Triggers;

/**
 * The life of one inbound event. Because events are stored before any work
 * happens, this status — not the presence of a run — is the record of what
 * became of an event.
 */
enum TriggerEventStatus: string
{
    /**
     * Stored and waiting for a worker.
     */
    case Queued = 'queued';

    /**
     * A worker has it right now.
     */
    case Running = 'running';

    /**
     * It started a run; run_id points at it.
     */
    case Fired = 'fired';

    /**
     * The trigger's config filters rejected it. Not a failure.
     */
    case Ignored = 'ignored';

    /**
     * The target was a draft or deleted — nothing to run.
     */
    case Skipped = 'skipped';

    /**
     * Signature verification failed.
     */
    case Rejected = 'rejected';

    /**
     * Every retry was spent without starting a run.
     */
    case Failed = 'failed';

    /**
     * A redelivery of something already accepted.
     *
     * Returned as an intake outcome only — never written to the status column,
     * because the row it refers to keeps whatever status it already had.
     */
    case Duplicate = 'duplicate';

    /**
     * Whether this event has reached a state it will never leave on its own.
     */
    public function isTerminal(): bool
    {
        return ! in_array($this, [self::Queued, self::Running], true);
    }

    public function isQueueable(): bool
    {
        return $this === self::Queued;
    }

    /**
     * Statuses the system has accepted responsibility for but not resolved —
     * the retry-stuck command's search space.
     *
     * @return array<int, string>
     */
    public static function unresolved(): array
    {
        return [self::Queued->value, self::Running->value];
    }
}
