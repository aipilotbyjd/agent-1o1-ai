<?php

namespace App\Nodes\Integrations\Semrush;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SemrushDomainPlaKeywordsNode extends AbstractSemrushReportNode
{
    public function type(): string
    {
        return 'semrush_domain_pla_keywords';
    }

    public function name(): string
    {
        return 'Semrush: Domain PLA Keywords';
    }

    public function description(): string
    {
        return 'The keywords that trigger a domain\'s product listing ads.';
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
                'sort' => $this->sortField(['po_asc', 'po_desc', 'nq_asc', 'nq_desc', 'pr_asc', 'pr_desc']),
                'filter' => $this->filterField(),
                'columns' => $this->columnsField(['Ph', 'Po', 'Pp', 'Pd', 'Nq', 'Sn', 'Ur', 'Tt', 'Pr', 'Ts']),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->seoReport($run, $config, 'domain_shopping', 'Ph,Po,Pp,Pd,Nq,Sn,Ur,Tt,Pr,Ts', ['domain' => $this->domain($config, 'domain', 'The domain')]);
    }
}
