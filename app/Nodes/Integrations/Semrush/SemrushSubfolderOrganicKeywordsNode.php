<?php

namespace App\Nodes\Integrations\Semrush;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SemrushSubfolderOrganicKeywordsNode extends AbstractSemrushReportNode
{
    public function type(): string
    {
        return 'semrush_subfolder_organic_keywords';
    }

    public function name(): string
    {
        return 'Semrush: Subfolder Organic Keywords';
    }

    public function description(): string
    {
        return 'The keywords that bring a subfolder traffic from organic search.';
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
                'date' => $this->dateField(),
                'daily' => Field::advanced(Field::boolean('Daily', 'Position changes day by day for the last 30 days or more.', false)),
                'positions' => Field::advanced(Field::select('Only keywords that are', ['new', 'lost', 'rise', 'fall'], 'Keywords that entered, left, rose in or fell in the top 100.')),
                'positions_type' => Field::advanced(Field::select('Position type', ['organic', 'all', 'serp_features'], 'Whether to count SERP feature positions.')),
                'sort' => $this->sortField(['po_asc', 'po_desc', 'tg_asc', 'tg_desc', 'tr_asc', 'tr_desc', 'tc_asc', 'tc_desc', 'nq_asc', 'nq_desc', 'co_asc', 'co_desc', 'kd_asc', 'kd_desc', 'cp_asc', 'cp_desc', 'nr_asc', 'nr_desc', 'ts_asc', 'ts_desc']),
                'filter' => $this->filterField(),
                'columns' => $this->columnsField(['Ph', 'Po', 'Pp', 'Pd', 'Nq', 'Cp', 'Ur', 'Tr', 'Tg', 'Tc', 'Co', 'Nr', 'Td', 'Kd', 'Fp', 'Fk', 'Ts', 'In', 'Pt']),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->seoReport($run, $config, 'subfolder_organic', 'Ph,Po,Pp,Pd,Nq,Cp,Ur,Tr,Tc,Co,Nr,Td', ['subfolder' => $this->requiredString($config, 'subfolder', 'The subfolder')]);
    }
}
