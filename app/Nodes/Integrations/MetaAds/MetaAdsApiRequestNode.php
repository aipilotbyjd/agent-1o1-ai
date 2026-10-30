<?php

namespace App\Nodes\Integrations\MetaAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class MetaAdsApiRequestNode extends AbstractMetaAdsNode
{
    public function type(): string
    {
        return 'meta_ads_api_request';
    }

    public function name(): string
    {
        return 'Meta Ads: Make An API Call';
    }

    public function description(): string
    {
        return 'Calls any Graph API endpoint with the connected account, for what no other node covers. Can change live ads and spend.';
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
                'method' => Field::select('Method', ['GET' => 'GET', 'POST' => 'POST', 'DELETE' => 'DELETE'], null, 'GET'),
                'path' => Field::text('Path', 'The address after the Graph API version.', '/me/adaccounts'),
                'query' => Field::keyValue('Parameters', 'Sent in the query, or as a form for POST. A nested value goes as JSON text.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $method = strtoupper(trim((string) ($config['method'] ?? 'GET')));

        if (! in_array($method, ['GET', 'POST', 'DELETE'], true)) {
            throw new InvalidArgumentException('The method must be GET, POST or DELETE.');
        }

        $path = '/'.ltrim($this->requiredString($config, 'path', 'The path'), '/');

        if (preg_match('#^/[A-Za-z0-9._~/-]*$#', $path) !== 1 || str_contains($path, '..') || str_contains($path, '//')) {
            throw new InvalidArgumentException('The path must start with / and hold no query; put those in Parameters.');
        }

        $response = $this->graphResponse($run, $config, $method, $path, (array) ($this->jsonOf($config, 'query', 'Parameters') ?? []));
        $this->assertOk($response);

        return ['status' => $response->status(), 'body' => $response->json()];
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<int|string, mixed>|null
     *
     * @throws InvalidArgumentException
     */
    private function jsonOf(array $config, string $key, string $label): ?array
    {
        $value = $config[$key] ?? null;

        if ($value === null || $value === '' || $value === []) {
            return null;
        }

        $decoded = is_string($value) ? json_decode($value, true) : $value;

        return is_array($decoded) ? $decoded : throw new InvalidArgumentException("{$label} must be JSON.");
    }
}
