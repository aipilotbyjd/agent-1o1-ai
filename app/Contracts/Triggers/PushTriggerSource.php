<?php

namespace App\Contracts\Triggers;

use App\Models\Triggers\Trigger;

/**
 * A provider whose events can start a trigger the moment they happen, instead
 * of waiting for the next poll.
 *
 * The poll path of the same trigger stays in place: it is the fallback while
 * push is down and the reconciliation that catches anything push missed. Both
 * paths must give an item the same id (itemId()), so the trigger's delivery id
 * stops one item running twice.
 *
 * @see docs/APP_TRIGGERS_PLAN.md "Delivery mechanisms"
 */
interface PushTriggerSource
{
    /**
     * The provider key events are filed under (`provider_events.provider`).
     */
    public function provider(): string;

    /**
     * Whether this provider's push is switched on and configured here.
     */
    public function isEnabled(): bool;

    /**
     * Whether the trigger is one this source can serve.
     */
    public function supports(Trigger $trigger): bool;

    /**
     * What provider events are matched on for this trigger (a Slack team id),
     * or null when it can't be known.
     *
     * May call the provider, so it only runs from a queued job.
     *
     * @throws \App\Exceptions\ConnectorException when the account needs reconnecting
     */
    public function routingKeyFor(Trigger $trigger): ?string;

    /**
     * Whether an item should start this trigger: its channel or resource and
     * its filters.
     *
     * @param  array<string, mixed>  $item
     */
    public function accepts(Trigger $trigger, array $item): bool;

    /**
     * The item a provider event carries, shaped like the item the poll path
     * produces for the same thing.
     *
     * @param  array<string, mixed>  $event
     * @return array<string, mixed>
     */
    public function itemFor(array $event): array;

    /**
     * The item's stable id: the same as the poll path gives it.
     *
     * @param  array<string, mixed>  $item
     */
    public function itemId(array $item): string;
}
