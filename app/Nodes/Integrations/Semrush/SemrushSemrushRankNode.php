<?php

namespace App\Nodes\Integrations\Semrush;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class SemrushSemrushRankNode extends AbstractSemrushReportNode
{
    public function type(): string
    {
        return 'semrush_semrush_rank';
    }

    public function name(): string
    {
        return 'Semrush: Semrush Rank';
    }

    public function description(): string
    {
        return 'The most popular websites by traffic from Google\'s top 100 organic results.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['database'],
            'properties' => [
                ...$this->credentialFields(),
                'database' => $this->databaseField(),
                'limit' => $this->limitField(),
                'offset' => $this->offsetField(),
                'date' => $this->dateField(),
                'filter' => $this->filterField(),
                'columns' => $this->columnsField(['Dn', 'Rk', 'Or', 'Ot', 'Oc', 'Ad', 'At', 'Ac', 'Sr', 'St', 'Sc']),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->seoReport($run, $config, 'rank', 'Dn,Rk,Or,Ot,Oc,Ad,At,Ac', []);
    }
}
