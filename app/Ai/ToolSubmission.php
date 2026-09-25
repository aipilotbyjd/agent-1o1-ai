<?php

namespace App\Ai;

use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Ai\Responses\TextResponse;
use RuntimeException;

/**
 * Reads the arguments of the tool an agent was forced to call to hand back
 * structured output. Tool-call arguments arrive as provider-parsed JSON,
 * which no model's text formatting habits (fences, prose, reasoning blocks)
 * can break.
 */
final class ToolSubmission
{
    /**
     * @return array<string, mixed>
     */
    public static function arguments(TextResponse $response, string $toolName): array
    {
        $call = $response->toolCalls->first(fn (ToolCall $call): bool => $call->name === $toolName);

        if ($call === null) {
            throw new RuntimeException("The model did not call {$toolName}. It may not support tool calling.");
        }

        return $call->arguments;
    }
}
