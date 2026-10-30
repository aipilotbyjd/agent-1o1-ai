<?php

namespace App\Nodes\Integrations\Apollo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApolloListFieldsNode extends AbstractApolloNode
{
    public function type(): string
    {
        return 'apollo_list_fields';
    }

    public function name(): string
    {
        return 'Apollo: List Fields';
    }

    public function description(): string
    {
        return 'Lists the system, custom and CRM-synced fields of records.';
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
                'source' => Field::select('Source', ['system' => 'System', 'custom' => 'Custom', 'crm_synced' => 'CRM synced']),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->listResult($this->apollo($run, $config, 'GET', '/fields', $this->only($config, ['source'])), 'fields');
    }
}
