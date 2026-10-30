<?php

namespace App\Nodes\Integrations\Sentry;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SentryDeleteSavedQueryNode extends AbstractSentryNode
{
    public function type(): string
    {
        return 'sentry_delete_saved_query';
    }

    public function name(): string
    {
        return 'Sentry: Delete Saved Query';
    }

    public function description(): string
    {
        return 'Deletes a saved Discover query.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
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
        $id = $this->pathId($config, 'query_id', 'The saved query id');
        $this->sentry($run, $config, 'DELETE', '/api/0/organizations/'.$this->org($run, $config)."/discover/saved/{$id}/");

        return ['deleted' => true, 'id' => $id];
    }
}
