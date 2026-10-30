<?php

namespace App\Nodes\Integrations\Semrush;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SemrushUrlOverviewNode extends AbstractSemrushReportNode
{
    public function type(): string
    {
        return 'semrush_url_overview';
    }

    public function name(): string
    {
        return 'Semrush: URL Overview';
    }

    public function description(): string
    {
        return 'Organic and paid rankings and traffic of a URL in one regional database.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['url', 'database'],
            'properties' => [
                ...$this->credentialFields(),
                'url' => Field::text('URL', null, 'https://example.com/page'),
                'database' => $this->databaseField(),
                'date' => $this->dateField(),
                'columns' => $this->columnsField(['Dn', 'Or', 'Xn', 'Ot', 'Oc', 'Ad', 'At', 'Ac', 'FKn', 'FPn', 'Ipu', 'Ip0', 'Ip1', 'Ip2', 'Ip3', 'Itu', 'It0', 'It1', 'It2', 'It3', 'Icu', 'Ic0', 'Ic1', 'Ic2', 'Ic3', 'Sr', 'Srb', 'St', 'Stb', 'Sc', 'Srn', 'Srl', 'Rk']),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->seoReport($run, $config, 'url_rank', 'Rk,Or,Ot,Oc,Ad,At,Ac', ['url' => $this->requiredString($config, 'url', 'The url')]);
    }
}
