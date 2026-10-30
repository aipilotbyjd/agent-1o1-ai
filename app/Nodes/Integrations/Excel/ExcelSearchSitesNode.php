<?php

namespace App\Nodes\Integrations\Excel;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ExcelSearchSitesNode extends AbstractExcelNode
{
    public function type(): string
    {
        return 'excel_search_sites';
    }

    public function name(): string
    {
        return 'Excel: Search SharePoint Sites';
    }

    public function description(): string
    {
        return 'Finds SharePoint sites by name, to reach their document libraries.';
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
                'query' => Field::text('Search for', null, 'Finance'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $sites = array_map(fn (array $site): array => ['id' => $site['id'] ?? null, 'name' => $site['displayName'] ?? $site['name'] ?? null, 'url' => $site['webUrl'] ?? null], array_values(array_filter((array) ($this->graph($run, $config, 'GET', '/sites', ['search' => $this->requiredString($config, 'query', 'The search')])['value'] ?? []), 'is_array')));

        return ['sites' => $sites, 'count' => count($sites)];
    }
}
