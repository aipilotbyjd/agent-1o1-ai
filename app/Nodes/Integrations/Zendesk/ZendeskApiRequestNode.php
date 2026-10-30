<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZendeskApiRequestNode extends AbstractZendeskNode
{
    public function type(): string
    {
        return 'zendesk_api_request';
    }

    public function name(): string
    {
        return 'Zendesk: Make an API Call';
    }

    public function description(): string
    {
        return 'Calls any Zendesk API address on this account, for what the other steps don\'t cover.';
    }

    public function effect(array $config): ActionEffect
    {
        return $this->effectOfMethod($config);
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['path'],
            'properties' => [
                ...$this->credentialFields(),
                'method' => Field::select('Method', ['GET' => 'GET', 'POST' => 'POST', 'PUT' => 'PUT', 'PATCH' => 'PATCH', 'DELETE' => 'DELETE'], null, 'GET'),
                'path' => Field::text('Path', 'After /api/v2.', '/brands'),
                'query' => Field::advanced(Field::json('Query (JSON)')),
                'body' => Field::advanced(Field::json('Body (JSON)')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $path = '/'.ltrim($this->requiredString($config, 'path', 'The path'), '/');
        if (preg_match('~^/[A-Za-z0-9_./-]*$~', $path) !== 1 || str_contains($path, '..') || str_contains($path, '//')) {
            throw new \InvalidArgumentException('The path must look like /tickets/123, with no address or query in it.');
        }
        $method = strtoupper((string) ($config['method'] ?? 'GET'));
        $body = $this->jsonObject($config, 'body', 'The body');
        $response = $this->zendeskResponse($run, $config, $method, $path, $this->jsonObject($config, 'query', 'The query'), $body === [] ? null : $body);
        $this->assertOk($response);

        return ['status' => $response->status(), 'body' => $response->json()];
    }
}
