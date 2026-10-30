<?php

namespace App\Nodes\Integrations\Semrush;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SemrushDomainAdHistoryNode extends AbstractSemrushReportNode
{
    public function type(): string
    {
        return 'semrush_domain_ad_history';
    }

    public function name(): string
    {
        return 'Semrush: Domain Ad History';
    }

    public function description(): string
    {
        return 'The keywords a domain has bid on in the last 12 months, and its ad positions.';
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
                'sort' => $this->sortField(['cv_asc', 'cv_desc']),
                'filter' => $this->filterField(),
                'columns' => $this->columnsField(['Ph', 'Dt', 'Po', 'Cp', 'Nq', 'Tr', 'Ur', 'Tt', 'Ds', 'Vu', 'Cv']),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->seoReport($run, $config, 'domain_adwords_historical', 'Ph,Dt,Po,Cp,Nq,Tr,Ur,Tt,Ds,Vu,Cv', ['domain' => $this->domain($config, 'domain', 'The domain')]);
    }
}
