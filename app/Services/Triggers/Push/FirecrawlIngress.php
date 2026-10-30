<?php

namespace App\Services\Triggers\Push;

use Illuminate\Http\Request;

/**
 * Firecrawl's monitor webhooks: a JSON body with a `type`
 * (`monitor.check.completed`), the check's `id`, a `webhookId` for the delivery and
 * `data`, posted to a URL with a token. The delivery id is the event id, so a retry is
 * stored once.
 */
class FirecrawlIngress extends AbstractTokenIngress
{
    public function provider(): string
    {
        return 'firecrawl';
    }

    public function events(Request $request): array
    {
        $type = (string) $request->json('type');

        if ($type === '') {
            return [];
        }

        return [[
            'event_id' => (string) ($request->json('webhookId') ?: sha1($this->routingKey($request).'|'.$request->getContent())),
            'event_type' => 'firecrawl.'.$type,
            'routing_key' => $this->routingKey($request),
            'payload' => ['type' => $type, 'id' => $request->json('id')],
        ]];
    }
}
