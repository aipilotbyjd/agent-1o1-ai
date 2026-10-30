<?php

namespace App\Nodes\Integrations\Semrush;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SemrushSubdomainOrganicPagesNode extends AbstractSemrushReportNode
{
    public function type(): string
    {
        return 'semrush_subdomain_organic_pages';
    }

    public function name(): string
    {
        return 'Semrush: Subdomain Organic Pages';
    }

    public function description(): string
    {
        return 'The pages of a subdomain ranking in Google\'s top 100 organic results.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['subdomain', 'database'],
            'properties' => [
                ...$this->credentialFields(),
                'subdomain' => Field::text('Subdomain', null, 'blog.example.com'),
                'database' => $this->databaseField(),
                'limit' => $this->limitField(),
                'offset' => $this->offsetField(),
                'date' => $this->dateField(),
                'sort' => $this->sortField(['pc_asc', 'pc_desc', 'tg_asc', 'tg_desc', 'tr_asc', 'tr_desc', 'sr_asc', 'sr_desc', 'st_asc', 'st_desc']),
                'filter' => $this->filterField(),
                'columns' => $this->columnsField(['Ur', 'Pc', 'Tg', 'Tr', 'Ipu', 'Ip0', 'Ip1', 'Ip2', 'Ip3', 'Itu', 'It0', 'It1', 'It2', 'It3', 'Sr', 'St']),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->seoReport($run, $config, 'subdomain_organic_unique', 'Ur,Pc,Tg,Tr', ['subdomain' => $this->domain($config, 'subdomain', 'The subdomain')]);
    }
}
