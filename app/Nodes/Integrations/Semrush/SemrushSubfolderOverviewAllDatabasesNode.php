<?php

namespace App\Nodes\Integrations\Semrush;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SemrushSubfolderOverviewAllDatabasesNode extends AbstractSemrushReportNode
{
    public function type(): string
    {
        return 'semrush_subfolder_overview_all_databases';
    }

    public function name(): string
    {
        return 'Semrush: Subfolder Overview (All Databases)';
    }

    public function description(): string
    {
        return 'Organic and paid rankings and traffic of a subfolder in every regional database.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['subfolder'],
            'properties' => [
                ...$this->credentialFields(),
                'subfolder' => Field::text('Subfolder', null, 'example.com/blog/'),
                'database' => Field::advanced($this->databaseField()),
                'limit' => $this->limitField(),
                'offset' => $this->offsetField(),
                'date' => $this->dateField(),
                'sort' => $this->sortField(['or_asc', 'or_desc', 'ot_asc', 'ot_desc', 'oc_asc', 'oc_desc', 'ad_asc', 'ad_desc', 'at_asc', 'at_desc', 'ac_asc', 'ac_desc']),
                'columns' => $this->columnsField(['Db', 'Dt', 'Dn', 'Or', 'Ot', 'Oc', 'Ad', 'At', 'Ac', 'Sh', 'Sv', 'FKn', 'FPn', 'Sr', 'Srb', 'St', 'Stb', 'Sc', 'Srn', 'Srl', 'Rk']),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->seoReport($run, $config, 'subfolder_ranks', 'Db,Rk,Or,Ot,Oc,Ad,At,Ac,Sh,Sv', ['subfolder' => $this->requiredString($config, 'subfolder', 'The subfolder')]);
    }
}
