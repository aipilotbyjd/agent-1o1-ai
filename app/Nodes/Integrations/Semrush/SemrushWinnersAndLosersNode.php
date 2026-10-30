<?php

namespace App\Nodes\Integrations\Semrush;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class SemrushWinnersAndLosersNode extends AbstractSemrushReportNode
{
    public function type(): string
    {
        return 'semrush_winners_and_losers';
    }

    public function name(): string
    {
        return 'Semrush: Winners and Losers';
    }

    public function description(): string
    {
        return 'The websites whose rankings, traffic and ad budget changed most.';
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
                'sort' => $this->sortField(['om_asc', 'om_desc', 'tm_asc', 'tm_desc', 'um_asc', 'um_desc', 'am_asc', 'am_desc', 'bm_asc', 'bm_desc', 'cm_asc', 'cm_desc', 'srm_desc', 'srm_asc', 'stm_desc', 'stm_asc', 'scm_desc', 'scm_asc']),
                'columns' => $this->columnsField(['Dn', 'Rk', 'Or', 'Ot', 'Oc', 'Ad', 'At', 'Ac', 'Om', 'Tm', 'Um', 'Am', 'Bm', 'Cm', 'Sr', 'St', 'Sc', 'Srm', 'Stm', 'Scm']),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->seoReport($run, $config, 'rank_difference', 'Dn,Rk,Or,Ot,Oc,Ad,At,Ac,Om,Tm,Um,Am,Bm,Cm', []);
    }
}
