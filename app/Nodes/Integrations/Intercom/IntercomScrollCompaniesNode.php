<?php

namespace App\Nodes\Integrations\Intercom;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class IntercomScrollCompaniesNode extends AbstractIntercomNode
{
    public function type(): string
    {
        return 'intercom_scroll_companies';
    }

    public function name(): string
    {
        return 'Intercom: List Companies (Large Datasets)';
    }

    public function description(): string
    {
        return 'Reads through every company, for workspaces with more than 10,000. Only one scroll can run at a time per workspace.';
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
                'limit' => Field::integer('Max results', null, 1000, 1, 10000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $records = [];
        $scroll = null;
        $limit = $this->limit($config, 1000, 10000);
        do {
            $answer = $this->intercom($run, $config, 'GET', '/companies/scroll', ['scroll_param' => $scroll]);
            $rows = array_values(array_filter((array) ($answer['data'] ?? []), 'is_array'));
            array_push($records, ...$rows);
            $scroll = $answer['scroll_param'] ?? null;
        } while ($rows !== [] && filled($scroll) && count($records) < $limit);
        $records = array_slice($records, 0, $limit);

        return ['companies' => array_map($this->shapeCompany(...), $records), 'count' => count($records)];
    }
}
