<?php

namespace App\Nodes\Integrations\Semrush;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SemrushDomainOrganicSubdomainsNode extends AbstractSemrushReportNode
{
    public function type(): string
    {
        return 'semrush_domain_organic_subdomains';
    }

    public function name(): string
    {
        return 'Semrush: Domain Organic Subdomains';
    }

    public function description(): string
    {
        return 'The subdomains of a domain ranking in Google\'s top 100 organic results.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['domain', 'database'],
            'properties' => [
                ...$this->credentialFields(),
                'domain' => Field::text('Domain', null, 'example.com'),
                'database' => $this->databaseField(),
                'limit' => $this->limitField(),
                'offset' => $this->offsetField(),
                'date' => $this->dateField(),
                'sort' => $this->sortField(['pc_asc', 'pc_desc', 'tg_asc', 'tg_desc', 'tr_asc', 'tr_desc', 'sr_asc', 'sr_desc', 'st_asc', 'st_desc']),
                'columns' => $this->columnsField(['Ur', 'Pc', 'Tg', 'Tr', 'Sr', 'St']),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->seoReport($run, $config, 'domain_organic_subdomains', 'Ur,Pc,Tg,Tr', ['domain' => $this->domain($config, 'domain', 'The domain')]);
    }
}
