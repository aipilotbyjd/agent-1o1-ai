<?php

namespace App\Nodes\Integrations\Semrush;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SemrushDomainOrganicCompetitorsNode extends AbstractSemrushReportNode
{
    public function type(): string
    {
        return 'semrush_domain_organic_competitors';
    }

    public function name(): string
    {
        return 'Semrush: Domain Organic Competitors';
    }

    public function description(): string
    {
        return 'The competitors of a domain in organic search.';
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
                'sort' => $this->sortField(['np_desc', 'np_asc', 'cr_desc', 'cr_asc']),
                'columns' => $this->columnsField(['Dn', 'Cr', 'Np', 'Or', 'Ot', 'Oc', 'Ad', 'Sr', 'St', 'Sc']),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->seoReport($run, $config, 'domain_organic_organic', 'Dn,Cr,Np,Or,Ot,Oc,Ad', ['domain' => $this->domain($config, 'domain', 'The domain')]);
    }
}
