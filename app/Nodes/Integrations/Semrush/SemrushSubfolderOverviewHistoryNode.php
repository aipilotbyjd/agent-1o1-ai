<?php

namespace App\Nodes\Integrations\Semrush;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SemrushSubfolderOverviewHistoryNode extends AbstractSemrushReportNode
{
    public function type(): string
    {
        return 'semrush_subfolder_overview_history';
    }

    public function name(): string
    {
        return 'Semrush: Subfolder Overview History';
    }

    public function description(): string
    {
        return 'Month by month rankings and traffic of a subfolder.';
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
                'limit' => $this->limitField(),
                'offset' => $this->offsetField(),
                'daily' => Field::advanced(Field::boolean('Daily', 'Position changes day by day for the last 30 days or more.', false)),
                'sort' => $this->sortField(['dt_asc', 'dt_desc']),
                'columns' => $this->columnsField(['Or', 'Xn', 'Ot', 'Oc', 'Ad', 'At', 'Ac', 'Dt', 'FKn', 'FPn', 'Sr', 'Srb', 'St', 'Stb', 'Sc', 'Rk']),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->seoReport($run, $config, 'subfolder_rank_history', 'Rk,Or,Ot,Oc,Ad,At,Ac,Dt', ['subfolder' => $this->requiredString($config, 'subfolder', 'The subfolder')]);
    }
}
