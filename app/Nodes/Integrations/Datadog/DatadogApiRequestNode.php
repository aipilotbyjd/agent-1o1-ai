<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class DatadogApiRequestNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_api_request';
    }

    public function name(): string
    {
        return 'Datadog: Make An API Call';
    }

    public function description(): string
    {
        return 'Calls any Datadog API endpoint with the connected keys, for what no other node covers.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['method', 'path'],
            'properties' => [
                ...$this->credentialFields(),
                'method' => Field::select('Method', ['GET' => 'GET', 'POST' => 'POST', 'PUT' => 'PUT', 'PATCH' => 'PATCH', 'DELETE' => 'DELETE'], null, 'GET'),
                'path' => Field::text('Path', 'On the connection\'s site.', '/api/v1/org'),
                'query' => Field::keyValue('Query'),
                'body' => Field::json('Body', 'Sent as JSON.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $path = '/'.ltrim($this->requiredString($config, 'path', 'The path'), '/');

        if (preg_match('#^/api/v[12]/[A-Za-z0-9._~:@/+-]*$#', $path) !== 1 || str_contains($path, '..')) {
            throw new InvalidArgumentException('The path must start with /api/v1/ or /api/v2/ and hold no query; put those in Query.');
        }

        $response = $this->datadogResponse($run, $config, $this->text($config, 'method') ?? 'GET', $path, (array) ($this->jsonOf($config, 'query', 'Query') ?? []), $this->jsonOf($config, 'body', 'Body'));
        $this->assertOk($response);

        return ['status' => $response->status(), 'body' => $response->json()];
    }
}
