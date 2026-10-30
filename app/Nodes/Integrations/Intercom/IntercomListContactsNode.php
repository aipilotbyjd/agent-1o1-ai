<?php

namespace App\Nodes\Integrations\Intercom;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class IntercomListContactsNode extends AbstractIntercomNode
{
    public function type(): string
    {
        return 'intercom_list_contacts';
    }

    public function name(): string
    {
        return 'Intercom: List Contacts';
    }

    public function description(): string
    {
        return 'Lists the workspace\'s contacts.';
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
                'limit' => Field::integer('Max results', null, 50, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $rows = $this->cursorAll($run, $config, '/contacts', 'data', [], null, $this->limit($config, 50));

        return ['contacts' => array_map($this->shapeContact(...), $rows), 'count' => count($rows)];
    }
}
