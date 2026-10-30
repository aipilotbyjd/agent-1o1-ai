<?php

namespace App\Services\Triggers\Push;

use Illuminate\Http\Request;

/**
 * Datadog's webhooks: one JSON object per notification, posted to a URL with a token.
 * Datadog's `$ID` names the event, so a retry of the same delivery is stored once.
 */
class DatadogIngress extends AbstractTokenIngress
{
    public function provider(): string
    {
        return 'datadog';
    }

    public function events(Request $request): array
    {
        $event = $request->json()->all();

        if (! is_array($event) || ! filled($event['id'] ?? null)) {
            return [];
        }

        return [[
            'event_id' => sha1($this->routingKey($request).'|'.$event['id'].'|'.($event['transition'] ?? '')),
            'event_type' => 'datadog.monitor',
            'routing_key' => $this->routingKey($request),
            'payload' => ['monitor_id' => $event['alert_id'] ?? null, 'transition' => $event['transition'] ?? null, 'title' => $event['title'] ?? null],
        ]];
    }
}
