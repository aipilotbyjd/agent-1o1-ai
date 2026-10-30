<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskSearchCompaniesNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_search_companies';
    }

    public function name(): string
    {
        return 'Freshdesk: Search Companies';
    }

    public function description(): string
    {
        return 'Finds companies with Freshdesk\'s filter language.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['query'],
            'properties' => [
                ...$this->credentialFields(),
                'query' => Field::text('Filter', 'Field names as in List Company Fields. Text values in single quotes.', 'domain:\'acme.com\''),
                'limit' => Field::integer('Max results', null, 30, 1, 300),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $rows = $this->searchAll($run, $config, '/search/companies', $this->requiredString($config, 'query', 'The filter'), $this->limit($config, 30, 300));

        return ['companies' => array_map($this->shapeCompany(...), $rows), 'count' => count($rows)];
    }
}
