<?php

namespace App\Nodes\Integrations\Sentry;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SentryGetSavedQueryNode extends AbstractSentryNode
{
    public function type(): string
    {
        return 'sentry_get_saved_query';
    }

    public function name(): string
    {
        return 'Sentry: Get Saved Query';
    }

    public function description(): string
    {
        return 'Gets a saved Discover query.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['query_id'],
            'properties' => [
                ...$this->credentialFields(),
                'organization' => $this->organizationField(),
                'query_id' => Field::text('Saved query id', 'From List Saved Queries.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $query = $this->find($run, $config, '/api/0/organizations/'.$this->org($run, $config).'/discover/saved/'.$this->pathId($config, 'query_id', 'The saved query id').'/');

        return $query === null ? ['found' => false] : ['found' => true, ...$query];
    }
}
