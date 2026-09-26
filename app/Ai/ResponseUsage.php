<?php

namespace App\Ai;

use Carbon\CarbonInterface;
use Laravel\Ai\Responses\TextResponse;

/**
 * What `CreditMeter` needs to price a model call like a Gumloop chat turn:
 * token usage, which model served it (so $-based pricing can look it up),
 * how many tool calls it made, and how long it ran.
 */
final class ResponseUsage
{
    /**
     * @return array<string, mixed>
     */
    public static function from(TextResponse $response, CarbonInterface $startedAt): array
    {
        return [
            ...$response->usage->toArray(),
            ...$response->meta->toArray(),
            'tool_call_count' => $response->toolCalls->count(),
            'duration_seconds' => $startedAt->diffInSeconds(now()),
        ];
    }
}
