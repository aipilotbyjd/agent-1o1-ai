<?php

namespace App\Nodes\Integrations\Sentry;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class SentryApiRequestNode extends AbstractSentryNode
{
    public function type(): string
    {
        return 'sentry_api_request';
    }

    public function name(): string
    {
        return 'Sentry: Make An API Call';
    }

    public function description(): string
    {
        return 'Calls any Sentry API endpoint with the connected token, for what no other node covers.';
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
                'method' => Field::select('Method', ['GET' => 'GET', 'POST' => 'POST', 'PUT' => 'PUT', 'DELETE' => 'DELETE'], null, 'GET'),
                'path' => Field::text('Path', null, '/api/0/organizations/'),
                'query' => Field::keyValue('Query'),
                'body' => Field::json('Body', 'Sent as JSON.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $path = '/'.ltrim($this->requiredString($config, 'path', 'The path'), '/');

        if (preg_match('#^/api/0/[A-Za-z0-9._~:@/+-]*$#', $path) !== 1 || str_contains($path, '..')) {
            throw new InvalidArgumentException('The path must start with /api/0/ and hold no query; put that in Query.');
        }

        $response = $this->sentryResponse($run, $config, $this->text($config, 'method') ?? 'GET', $path, (array) ($this->jsonOf($config, 'query', 'Query') ?? []), $this->jsonOf($config, 'body', 'Body'));
        $this->assertOk($response);

        return ['status' => $response->status(), 'body' => $response->json(), 'next_cursor' => self::nextCursor($response)];
    }
}
