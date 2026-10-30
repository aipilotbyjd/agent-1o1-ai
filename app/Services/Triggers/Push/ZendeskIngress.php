<?php

namespace App\Services\Triggers\Push;

use Illuminate\Http\Request;

/**
 * Zendesk's webhooks: one JSON event (`id`, `type`, `subject`, `detail`) posted to a
 * URL with a token. The event's own id is used, so a retry is stored once; a body
 * without one gets an id made from the token and the content.
 */
class ZendeskIngress extends AbstractTokenIngress
{
    public function provider(): string
    {
        return 'zendesk';
    }

    public function events(Request $request): array
    {
        $event = $request->json()->all();

        if (! is_array($event) || ! filled($event['type'] ?? null)) {
            return [];
        }

        return [[
            'event_id' => filled($event['id'] ?? null) ? 'zendesk:'.$event['id'] : sha1($this->routingKey($request).'|'.json_encode($event)),
            'event_type' => 'zendesk.'.strtolower(str_replace('zen:event-type:', '', (string) $event['type'])),
            'routing_key' => $this->routingKey($request),
            'payload' => ['event' => $event['type'], 'subject' => $event['subject'] ?? null, 'id' => data_get($event, 'detail.id')],
        ]];
    }
}
