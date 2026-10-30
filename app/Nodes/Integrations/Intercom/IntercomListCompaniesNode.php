<?php

namespace App\Nodes\Integrations\Intercom;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class IntercomListCompaniesNode extends AbstractIntercomNode
{
    public function type(): string
    {
        return 'intercom_list_companies';
    }

    public function name(): string
    {
        return 'Intercom: List Companies';
    }

    public function description(): string
    {
        return 'Lists companies, newest first.';
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
        $rows = $this->pagedAll($run, $config, '/companies/list', 'data', ['order' => 'desc'], $this->limit($config, 50), 'POST');

        return ['companies' => array_map($this->shapeCompany(...), $rows), 'count' => count($rows)];
    }
}
