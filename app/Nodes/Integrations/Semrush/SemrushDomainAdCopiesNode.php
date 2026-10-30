<?php

namespace App\Nodes\Integrations\Semrush;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SemrushDomainAdCopiesNode extends AbstractSemrushReportNode
{
    public function type(): string
    {
        return 'semrush_domain_ad_copies';
    }

    public function name(): string
    {
        return 'Semrush: Domain Ad Copies';
    }

    public function description(): string
    {
        return 'The unique ads a domain has shown in paid search.';
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
                'sort' => $this->sortField(['pc_asc', 'pc_desc']),
                'filter' => $this->filterField(),
                'columns' => $this->columnsField(['Ph', 'Un', 'Tt', 'Ds', 'Vu', 'Ur', 'Pc', 'Ts']),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->seoReport($run, $config, 'domain_adwords_unique', 'Tt,Ds,Vu,Ur,Pc,Un', ['domain' => $this->domain($config, 'domain', 'The domain')]);
    }
}
