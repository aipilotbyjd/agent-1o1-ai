<?php

namespace App\Nodes\Integrations\Intercom;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class IntercomSearchCompaniesNode extends AbstractIntercomNode
{
    public function type(): string
    {
        return 'intercom_search_companies';
    }

    public function name(): string
    {
        return 'Intercom: Search Companies';
    }

    public function description(): string
    {
        return 'Finds companies by a field, such as name, company_id, a tag or a custom attribute.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['field', 'value'],
            'properties' => [
                ...$this->credentialFields(),
                'field' => Field::text('Field', 'e.g. name, company_id, tag_id, custom_attributes.plan.', 'name'),
                'operator' => Field::advanced(Field::select('Match', ['=' => 'Is', '!=' => 'Is not', '~' => 'Contains', '!~' => 'Does not contain', '^' => 'Starts with', '$' => 'Ends with'], null, '=')),
                'value' => Field::text('Value'),
                'limit' => Field::integer('Max results', null, 50, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $rows = $this->cursorAll($run, $config, '/companies/search', 'data', [], ['field' => $this->requiredString($config, 'field', 'The field'), 'operator' => trim((string) ($config['operator'] ?? '=')), 'value' => $this->requiredString($config, 'value', 'The value')], $this->limit($config, 50));

        return ['companies' => array_map($this->shapeCompany(...), $rows), 'count' => count($rows)];
    }
}
