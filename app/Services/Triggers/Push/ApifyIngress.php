<?php

namespace App\Services\Triggers\Push;

use Illuminate\Http\Request;

/**
 * Apify's webhooks: a JSON body with an `eventType` (`ACTOR.RUN.SUCCEEDED`),
 * `eventData` and the run or build as `resource`, posted to a URL with a token.
 * Apify's dispatch id (`X-Apify-Webhook-Dispatch-Id`) is the event id, so a retry is
 * stored once. The Test button's event starts nothing.
 */
class ApifyIngress extends AbstractTokenIngress
{
    public function provider(): string
    {
        return 'apify';
    }

    public function events(Request $request): array
    {
        $type = (string) $request->json('eventType');

        if ($type === '' || $type === 'TEST') {
            return [];
        }

        return [[
            'event_id' => (string) ($request->header('X-Apify-Webhook-Dispatch-Id') ?: sha1($this->routingKey($request).'|'.$request->getContent())),
            'event_type' => 'apify.'.strtolower($type),
            'routing_key' => $this->routingKey($request),
            'payload' => ['type' => $type, 'id' => $request->json('resource.id') ?? $request->json('eventData.actorRunId')],
        ]];
    }
}
