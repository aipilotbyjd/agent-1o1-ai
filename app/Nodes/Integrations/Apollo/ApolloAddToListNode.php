<?php

namespace App\Nodes\Integrations\Apollo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApolloAddToListNode extends AbstractApolloNode
{
    public function type(): string
    {
        return 'apollo_add_to_list';
    }

    public function name(): string
    {
        return 'Apollo: Add Records to List';
    }

    public function description(): string
    {
        return 'Adds contacts or accounts to one or more lists.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['entity_ids', 'label_names', 'modality'],
            'properties' => [
                ...$this->credentialFields(),
                'entity_ids' => Field::list('Record ids', 'The contact or account ids.'),
                'label_names' => Field::list('List names'),
                'modality' => Field::select('Records are', ['contacts' => 'Contacts', 'accounts' => 'Accounts'], null, 'contacts'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $body = $this->apollo($run, $config, 'POST', '/labels/add_entity_ids_to_label_names', [], ['entity_ids' => $this->requiredList($config, 'entity_ids', 'The record ids'), 'label_names' => $this->requiredList($config, 'label_names', 'The list names'), 'modality' => $this->requiredString($config, 'modality', 'The kind of record')]);

        return ['lists' => array_values((array) ($body['labels'] ?? [])), 'message' => $body['message'] ?? null];
    }
}
