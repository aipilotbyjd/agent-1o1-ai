<?php

namespace App\Nodes\Integrations\Semrush;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SemrushSubdomainAdCopiesNode extends AbstractSemrushReportNode
{
    public function type(): string
    {
        return 'semrush_subdomain_ad_copies';
    }

    public function name(): string
    {
        return 'Semrush: Subdomain Ad Copies';
    }

    public function description(): string
    {
        return 'The unique ads a subdomain has shown in paid search.';
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
                'sort' => $this->sortField(['pc_asc', 'pc_desc']),
                'filter' => $this->filterField(),
                'columns' => $this->columnsField(['Ph', 'Un', 'Tt', 'Ds', 'Vu', 'Ur', 'Pc', 'Ts']),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->seoReport($run, $config, 'subdomain_adwords_unique', 'Tt,Ds,Vu,Ur,Pc,Un', ['subdomain' => $this->domain($config, 'subdomain', 'The subdomain')]);
    }
}
