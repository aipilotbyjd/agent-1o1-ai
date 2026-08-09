<?php

namespace App\Enums\Triggers;

/**
 * How a trigger fires. Also used for trigger_events.source — an event's source
 * is always the type of the trigger that produced it.
 */
enum TriggerType: string
{
    case Webhook = 'webhook';
    case Schedule = 'schedule';
    case Polling = 'polling';
    case Manual = 'manual';

    /**
     * Only webhooks get a public URL token.
     */
    public function usesToken(): bool
    {
        return $this === self::Webhook;
    }

    /**
     * Only polling triggers authenticate an outbound request.
     */
    public function usesCredential(): bool
    {
        return $this === self::Polling;
    }
}
