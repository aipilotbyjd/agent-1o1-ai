<?php

namespace App\Nodes\Integrations\Semrush;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SemrushDomainPlaCompetitorsNode extends AbstractSemrushReportNode
{
    public function type(): string
    {
        return 'semrush_domain_pla_competitors';
    }

    public function name(): string
    {
        return 'Semrush: Domain PLA Competitors';
    }

    public function description(): string
    {
        return 'The domains competing with a domain through product listing ads.';
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
                'sort' => $this->sortField(['np_asc', 'np_desc', 'cr_asc', 'cr_desc']),
                'columns' => $this->columnsField(['Dn', 'Cr', 'Np', 'Sh', 'Ad', 'At', 'Ac', 'Or']),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->seoReport($run, $config, 'domain_shopping_shopping', 'Dn,Cr,Np,Ad,At,Ac,Or,Sh', ['domain' => $this->domain($config, 'domain', 'The domain')]);
    }
}
