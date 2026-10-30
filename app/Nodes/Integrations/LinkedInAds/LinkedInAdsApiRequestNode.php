<?php

namespace App\Nodes\Integrations\LinkedInAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class LinkedInAdsApiRequestNode extends AbstractLinkedInAdsNode
{
    public function type(): string
    {
        return 'linkedin_ads_api_request';
    }

    public function name(): string
    {
        return 'LinkedIn Ads: Make An API Call';
    }

    public function description(): string
    {
        return 'Calls any LinkedIn Marketing API endpoint with the connected account, for what no other node covers. Can change live ads and spend.';
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
                'path' => Field::text('Path', 'The address after /rest, with any URN already encoded.', '/adAccounts/123/adCampaigns'),
                'query' => Field::text('Query', 'The query text as LinkedIn documents it, brackets and all, without the leading ?.', 'q=search&search=(status:(values:List(ACTIVE)))'),
                'body' => Field::json('Body', 'Sent as JSON.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $method = strtoupper(trim((string) ($config['method'] ?? 'GET')));

        if (! in_array($method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            throw new InvalidArgumentException('The method must be GET, POST, PUT, PATCH or DELETE.');
        }

        $path = '/'.ltrim($this->requiredString($config, 'path', 'The path'), '/');

        if (preg_match('#^/[A-Za-z0-9._~/:%(),-]*$#', $path) !== 1 || str_contains($path, '..') || str_contains($path, '//')) {
            throw new InvalidArgumentException('The path must start with / and hold no query; put those in Query.');
        }

        $query = trim((string) ($config['query'] ?? ''));

        if (str_contains($query, '#')) {
            throw new InvalidArgumentException('The query can\'t hold a #.');
        }

        $response = $this->linkedinResponse($run, $config, $method, $path, ltrim($query, '?'), $this->jsonOf($config, 'body', 'Body'));
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
