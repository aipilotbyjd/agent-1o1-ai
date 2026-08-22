<?php

namespace App\Nodes\DataTransform;

use App\Contracts\NodeContract;
use App\Enums\NodeCategory;
use App\Models\Runs\Run;
use Illuminate\Support\Facades\Http;

/**
 * Generic outbound HTTP request node (Gumloop's "Call API"/`CallApiNode`).
 */
class CallApiNode implements NodeContract
{
    public function type(): string
    {
        return 'call_api';
    }

    public function category(): string
    {
        return NodeCategory::DataTransform->value;
    }

    public function name(): string
    {
        return 'Call API';
    }

    public function description(): string
    {
        return 'Makes a generic outbound HTTP request and returns the status, headers, and body.';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['method', 'url'],
            'properties' => [
                'method' => ['type' => 'string', 'enum' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE']],
                'url' => ['type' => 'string'],
                'headers' => ['type' => 'object'],
                'body' => ['type' => 'object'],
                'timeout_seconds' => ['type' => 'integer'],
            ],
        ];
    }

    public function outputSchema(array $config = []): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'status' => ['type' => 'integer'],
                'headers' => ['type' => 'object'],
                // Whatever the endpoint returns — decoded JSON when it is
                // JSON, the raw body string when it isn't.
                'body' => [],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $response = Http::withHeaders($config['headers'] ?? [])
            ->timeout((int) ($config['timeout_seconds'] ?? 30))
            ->send(
                strtoupper($config['method'] ?? 'GET'),
                $config['url'],
                ['json' => $config['body'] ?? []],
            );

        return [
            'status' => $response->status(),
            'headers' => $response->headers(),
            'body' => $response->json() ?? $response->body(),
        ];
    }
}
