<?php

namespace App\Nodes\Integrations\Semrush;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SemrushDomainOverviewNode extends AbstractSemrushReportNode
{
    public function type(): string
    {
        return 'semrush_domain_overview';
    }

    public function name(): string
    {
        return 'Semrush: Domain Overview';
    }

    public function description(): string
    {
        return 'Organic and paid rankings, traffic and cost of a domain in one regional database.';
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
                'date' => $this->dateField(),
                'columns' => $this->columnsField(['Dn', 'Rk', 'Or', 'Xn', 'Ot', 'Oc', 'Ad', 'At', 'Ac', 'FKn', 'FPn', 'Ipu', 'Ip0', 'Ip1', 'Ip2', 'Ip3', 'Itu', 'It0', 'It1', 'It2', 'It3', 'Icu', 'Ic0', 'Ic1', 'Ic2', 'Ic3', 'Sr', 'Srb', 'St', 'Stb', 'Sc', 'Srn', 'Srl']),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->seoReport($run, $config, 'domain_rank', 'Dn,Rk,Or,Ot,Oc,Ad,At,Ac', ['domain' => $this->domain($config, 'domain', 'The domain')]);
    }
}
