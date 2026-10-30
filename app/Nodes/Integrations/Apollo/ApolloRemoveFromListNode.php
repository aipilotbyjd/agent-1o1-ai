<?php

namespace App\Nodes\Integrations\Apollo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApolloRemoveFromListNode extends AbstractApolloNode
{
    public function type(): string
    {
        return 'apollo_remove_from_list';
    }

    public function name(): string
    {
        return 'Apollo: Remove Records from List';
    }

    public function description(): string
    {
        return 'Takes contacts or accounts off one or more lists. The records stay in Apollo.';
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
        $body = $this->apollo($run, $config, 'POST', '/labels/remove_entity_ids_from_label_names', [], ['entity_ids' => $this->requiredList($config, 'entity_ids', 'The record ids'), 'label_names' => $this->requiredList($config, 'label_names', 'The list names'), 'modality' => $this->requiredString($config, 'modality', 'The kind of record')]);

        return ['lists' => array_values((array) ($body['labels'] ?? [])), 'message' => $body['message'] ?? null];
    }
}
