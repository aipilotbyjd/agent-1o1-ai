<?php

namespace App\Nodes\Integrations\Semrush;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SemrushDomainOverviewHistoryNode extends AbstractSemrushReportNode
{
    public function type(): string
    {
        return 'semrush_domain_overview_history';
    }

    public function name(): string
    {
        return 'Semrush: Domain Overview History';
    }

    public function description(): string
    {
        return 'Month by month rankings, traffic and cost of a domain.';
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
                'daily' => Field::advanced(Field::boolean('Daily', 'Position changes day by day for the last 30 days or more.', false)),
                'sort' => $this->sortField(['dt_asc', 'dt_desc']),
                'columns' => $this->columnsField(['Rk', 'Or', 'Xn', 'Ot', 'Oc', 'Ad', 'At', 'Ac', 'Dt', 'FKn', 'FPn', 'Sr', 'Srb', 'St', 'Stb', 'Sc']),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->seoReport($run, $config, 'domain_rank_history', 'Rk,Or,Ot,Oc,Ad,At,Ac,Dt', ['domain' => $this->domain($config, 'domain', 'The domain')]);
    }
}
