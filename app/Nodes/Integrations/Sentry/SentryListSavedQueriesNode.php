<?php

namespace App\Nodes\Integrations\Sentry;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SentryListSavedQueriesNode extends AbstractSentryNode
{
    public function type(): string
    {
        return 'sentry_list_saved_queries';
    }

    public function name(): string
    {
        return 'Sentry: List Saved Queries';
    }

    public function description(): string
    {
        return 'Lists an organization\'s saved Discover queries.';
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
                'organization' => $this->organizationField(),
                'query' => Field::text('Name contains'),
                ...$this->pageFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->listOf($run, $config, '/api/0/organizations/'.$this->org($run, $config).'/discover/saved/', 'queries', ['query' => $this->text($config, 'query'), 'per_page' => $this->limit($config)]);
    }
}
