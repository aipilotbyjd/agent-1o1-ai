<?php

namespace App\Nodes\Integrations\Sentry;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SentryListOrganizationsNode extends AbstractSentryNode
{
    public function type(): string
    {
        return 'sentry_list_organizations';
    }

    public function name(): string
    {
        return 'Sentry: List Organizations';
    }

    public function description(): string
    {
        return 'Lists the organizations the auth token can reach.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
                'query' => Field::text('Search'),
                ...$this->pageFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->listOf($run, $config, '/api/0/organizations/', 'organizations', ['query' => $this->text($config, 'query'), 'per_page' => $this->limit($config)], fn (array $organization): array => ['id' => $organization['id'] ?? null, 'slug' => $organization['slug'] ?? null, 'name' => $organization['name'] ?? null, 'status' => data_get($organization, 'status.id'), 'created_at' => $organization['dateCreated'] ?? null]);
    }
}
