<?php

namespace App\Services\Triggers\Push;

use Illuminate\Http\Request;

/**
 * Exa's Websets webhooks: a JSON event with an `id`, a `type` (`webset.item.created`)
 * and `data`, posted to a URL with a token. The event's id is the event id, so a
 * retry is stored once.
 */
class ExaIngress extends AbstractTokenIngress
{
    public function provider(): string
    {
        return 'exa';
    }

    public function events(Request $request): array
    {
        $type = (string) $request->json('type');

        if ($type === '') {
            return [];
        }

        return [[
            'event_id' => (string) ($request->json('id') ?: sha1($this->routingKey($request).'|'.$request->getContent())),
            'event_type' => 'exa.'.$type,
            'routing_key' => $this->routingKey($request),
            'payload' => ['type' => $type, 'id' => $request->json('data.id')],
        ]];
    }
}
