<?php

namespace App\Nodes\Integrations\Semrush;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SemrushSubfolderOverviewNode extends AbstractSemrushReportNode
{
    public function type(): string
    {
        return 'semrush_subfolder_overview';
    }

    public function name(): string
    {
        return 'Semrush: Subfolder Overview';
    }

    public function description(): string
    {
        return 'Organic and paid rankings and traffic of a subfolder in one regional database.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['subfolder', 'database'],
            'properties' => [
                ...$this->credentialFields(),
                'subfolder' => Field::text('Subfolder', null, 'example.com/blog/'),
                'database' => $this->databaseField(),
                'date' => $this->dateField(),
                'columns' => $this->columnsField(['Dn', 'Or', 'Xn', 'Ot', 'Oc', 'Ad', 'At', 'Ac', 'FKn', 'FPn', 'Ipu', 'Ip0', 'Ip1', 'Ip2', 'Ip3', 'Itu', 'It0', 'It1', 'It2', 'It3', 'Icu', 'Ic0', 'Ic1', 'Ic2', 'Ic3', 'Sr', 'Srb', 'St', 'Stb', 'Sc', 'Srn', 'Srl', 'Rk']),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->seoReport($run, $config, 'subfolder_rank', 'Rk,Or,Ot,Oc,Ad,At,Ac', ['subfolder' => $this->requiredString($config, 'subfolder', 'The subfolder')]);
    }
}
