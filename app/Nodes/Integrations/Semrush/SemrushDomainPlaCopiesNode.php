<?php

namespace App\Nodes\Integrations\Semrush;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SemrushDomainPlaCopiesNode extends AbstractSemrushReportNode
{
    public function type(): string
    {
        return 'semrush_domain_pla_copies';
    }

    public function name(): string
    {
        return 'Semrush: Domain PLA Copies';
    }

    public function description(): string
    {
        return 'The product listing ads a domain has shown.';
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
                'sort' => $this->sortField(['pr_asc', 'pr_desc', 'pc_asc', 'pc_desc']),
                'filter' => $this->filterField(),
                'columns' => $this->columnsField(['Tt', 'Pr', 'Ur', 'Pc', 'Un', 'Ts']),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->seoReport($run, $config, 'domain_shopping_unique', 'Tt,Pr,Ur,Pc,Un', ['domain' => $this->domain($config, 'domain', 'The domain')]);
    }
}
