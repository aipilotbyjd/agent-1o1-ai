<?php

namespace App\Nodes\Integrations\Sentry;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SentryUpdateSavedQueryNode extends AbstractSentryNode
{
    public function type(): string
    {
        return 'sentry_update_saved_query';
    }

    public function name(): string
    {
        return 'Sentry: Update Saved Query';
    }

    public function description(): string
    {
        return 'Replaces a saved Discover query.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['query_id', 'name'],
            'properties' => [
                ...$this->credentialFields(),
                'organization' => $this->organizationField(),
                'query_id' => Field::text('Saved query id', 'From List Saved Queries.'),
                'name' => Field::text('Name'),
                'query' => Field::text('Search', 'Sentry search syntax.', 'is:unresolved level:error'),
                'fields' => Field::list('Fields', 'Like title and count().'),
                'range' => Field::text('Period', 'Like 14d.'),
                'orderby' => Field::advanced(Field::text('Order by')),
                'query_dataset' => Field::advanced(Field::select('Dataset', ['discover' => 'Discover', 'error-events' => 'Errors', 'transaction-like' => 'Transactions'])),
                'project_ids' => Field::advanced(Field::list('Project ids', 'Numeric ids.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $body = [...$this->filledOf($config, ['name' => 'name', 'query' => 'query', 'range' => 'range', 'orderby' => 'orderby', 'query_dataset' => 'queryDataset']), 'version' => 2];

        if ($this->items($config, 'fields') !== []) {
            $body['fields'] = $this->items($config, 'fields');
        }

        if ($this->items($config, 'project_ids') !== []) {
            $body['projects'] = array_map('intval', $this->items($config, 'project_ids'));
        }

        $this->requiredString($config, 'name', 'The name');

        return $this->sentry($run, $config, 'PUT', '/api/0/organizations/'.$this->org($run, $config).'/discover/saved/'.$this->pathId($config, 'query_id', 'The saved query id').'/', [], $body);
    }
}
