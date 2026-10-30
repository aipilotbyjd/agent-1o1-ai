<?php

namespace App\Nodes\Integrations\Apollo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApolloUpdateListNode extends AbstractApolloNode
{
    public function type(): string
    {
        return 'apollo_update_list';
    }

    public function name(): string
    {
        return 'Apollo: Update List';
    }

    public function description(): string
    {
        return 'Renames a list.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['list_id', 'name'],
            'properties' => [
                ...$this->credentialFields(),
                'list_id' => Field::dynamic('List', 'apollo.lists'),
                'name' => Field::text('New name'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $body = $this->apollo($run, $config, 'PATCH', '/labels/'.$this->recordId($config, 'list_id', 'The list'), [], ['name' => $this->requiredString($config, 'name', 'The name')]);

        return ['list' => $body['label'] ?? $body];
    }
}
