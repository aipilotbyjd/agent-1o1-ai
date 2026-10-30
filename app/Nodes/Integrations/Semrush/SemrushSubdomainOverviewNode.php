<?php

namespace App\Nodes\Integrations\Semrush;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SemrushSubdomainOverviewNode extends AbstractSemrushReportNode
{
    public function type(): string
    {
        return 'semrush_subdomain_overview';
    }

    public function name(): string
    {
        return 'Semrush: Subdomain Overview';
    }

    public function description(): string
    {
        return 'Organic and paid rankings and traffic of a subdomain in one regional database.';
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
                'date' => $this->dateField(),
                'columns' => $this->columnsField(['Dn', 'Or', 'Xn', 'Ot', 'Oc', 'Ad', 'At', 'Ac', 'FKn', 'FPn', 'Ipu', 'Ip0', 'Ip1', 'Ip2', 'Ip3', 'Itu', 'It0', 'It1', 'It2', 'It3', 'Icu', 'Ic0', 'Ic1', 'Ic2', 'Ic3', 'Sr', 'Srb', 'St', 'Stb', 'Sc', 'Srn', 'Srl', 'Rk']),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->seoReport($run, $config, 'subdomain_rank', 'Rk,Or,Ot,Oc,Ad,At,Ac', ['subdomain' => $this->domain($config, 'subdomain', 'The subdomain')]);
    }
}
