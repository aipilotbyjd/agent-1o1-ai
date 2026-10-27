<?php

namespace App\Services\Triggers;

use App\Enums\Triggers\TriggerType;
use App\Models\Triggers\Trigger;
use InvalidArgumentException;

/**
 * Shows what a polling trigger sees right now, so a person can map fields
 * from real data before the first event. Reads like a poll does, but records
 * nothing and starts nothing.
 */
class TriggerSampler
{
    public function __construct(private readonly PollReader $reader) {}

    /**
     * The newest items, newest first, as a run would receive them.
     *
     * Each one may cost a call to the app (Gmail fetches every message), so
     * only a few are shown.
     *
     * @return list<array{id: string, payload: array<string, mixed>}>
     *
     * @throws InvalidArgumentException when the trigger has nothing to read
     */
    public function sample(Trigger $trigger, int $limit = 5): array
    {
        if ($trigger->type !== TriggerType::Polling) {
            throw new InvalidArgumentException('Only a trigger that reads from an app or URL has a sample.');
        }

        [$items, $idOf, $expand] = $this->reader->read($trigger);

        return array_map(
            fn (array $item): array => ['id' => $idOf($item), 'payload' => $expand($item)],
            array_slice(array_reverse($items), 0, max(1, min($limit, 10))),
        );
    }
}
