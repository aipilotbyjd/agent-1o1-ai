<?php

namespace App\Nodes\Integrations\Semrush;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SemrushSubdomainOverviewHistoryNode extends AbstractSemrushReportNode
{
    public function type(): string
    {
        return 'semrush_subdomain_overview_history';
    }

    public function name(): string
    {
        return 'Semrush: Subdomain Overview History';
    }

    public function description(): string
    {
        return 'Month by month rankings and traffic of a subdomain.';
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
        return $this->seoReport($run, $config, 'subdomain_rank_history', 'Rk,Or,Ot,Oc,Ad,At,Ac,Dt', ['subdomain' => $this->domain($config, 'subdomain', 'The subdomain')]);
    }
}
