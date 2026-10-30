<?php

namespace App\Nodes\Integrations\Intercom;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class IntercomListDataAttributesNode extends AbstractIntercomNode
{
    public function type(): string
    {
        return 'intercom_list_data_attributes';
    }

    public function name(): string
    {
        return 'Intercom: List Data Attributes';
    }

    public function description(): string
    {
        return 'Lists the data attributes of contacts or companies.';
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
                'model' => Field::advanced(Field::select('Of', ['contact' => 'Contacts', 'company' => 'Companies'])),
                'limit' => Field::integer('Max results', null, 500, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $rows = (array) ($this->intercom($run, $config, 'GET', '/data_attributes', ['model' => $this->text($config, 'model')])['data'] ?? []);
        $rows = array_slice($rows, 0, $this->limit($config, 500));

        return ['data_attributes' => $rows, 'count' => count($rows)];
    }
}
