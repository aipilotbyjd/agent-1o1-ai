<?php

namespace App\Nodes\Integrations\Apollo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ApolloListCustomFieldsNode extends AbstractApolloNode
{
    public function type(): string
    {
        return 'apollo_list_custom_fields';
    }

    public function name(): string
    {
        return 'Apollo: List Custom Fields';
    }

    public function description(): string
    {
        return 'Lists custom fields with the ids that record updates need.';
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
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->listResult($this->apollo($run, $config, 'GET', '/typed_custom_fields'), 'typed_custom_fields');
    }
}
