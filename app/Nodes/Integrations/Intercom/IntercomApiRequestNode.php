<?php

namespace App\Nodes\Integrations\Intercom;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class IntercomApiRequestNode extends AbstractIntercomNode
{
    public function type(): string
    {
        return 'intercom_api_request';
    }

    public function name(): string
    {
        return 'Intercom: Make an API Call';
    }

    public function description(): string
    {
        return 'Calls any Intercom API address on this workspace, for what the other steps don\'t cover.';
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
                'method' => Field::select('Method', ['GET' => 'GET', 'POST' => 'POST', 'PUT' => 'PUT', 'DELETE' => 'DELETE'], null, 'GET'),
                'path' => Field::text('Path', 'Starts with /.', '/brands'),
                'query' => Field::advanced(Field::json('Query (JSON)')),
                'body' => Field::advanced(Field::json('Body (JSON)')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $path = '/'.ltrim($this->requiredString($config, 'path', 'The path'), '/');
        if (preg_match('~^/[A-Za-z0-9_./-]*$~', $path) !== 1 || str_contains($path, '..') || str_contains($path, '//')) {
            throw new \InvalidArgumentException('The path must look like /contacts/123, with no address or query in it.');
        }
        $body = $this->jsonObject($config, 'body', 'The body');
        $response = $this->intercomResponse($run, $config, strtoupper((string) ($config['method'] ?? 'GET')), $path, $this->jsonObject($config, 'query', 'The query'), $body === [] ? null : $body);
        $this->assertOk($response);

        return ['status' => $response->status(), 'body' => $response->json()];
    }
}
