<?php

namespace App\Nodes\Integrations\Intercom;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class IntercomListCompanyContactsNode extends AbstractIntercomNode
{
    public function type(): string
    {
        return 'intercom_list_company_contacts';
    }

    public function name(): string
    {
        return 'Intercom: List Company\'s Contacts';
    }

    public function description(): string
    {
        return 'Lists the contacts of a company.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['id'],
            'properties' => [
                ...$this->credentialFields(),
                'id' => Field::text('Company id', 'Intercom\'s id of the company.'),
                'limit' => Field::integer('Max results', null, 50, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->safeId($config, 'id', 'The company');
        $rows = $this->cursorAll($run, $config, "/companies/{$id}/contacts", 'data', [], null, $this->limit($config, 50));

        return ['contacts' => array_map($this->shapeContact(...), $rows), 'count' => count($rows)];
    }
}
