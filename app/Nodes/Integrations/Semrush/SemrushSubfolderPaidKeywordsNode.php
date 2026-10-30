<?php

namespace App\Nodes\Integrations\Semrush;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SemrushSubfolderPaidKeywordsNode extends AbstractSemrushReportNode
{
    public function type(): string
    {
        return 'semrush_subfolder_paid_keywords';
    }

    public function name(): string
    {
        return 'Semrush: Subfolder Paid Keywords';
    }

    public function description(): string
    {
        return 'The keywords that bring a subfolder traffic from paid search.';
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
                'positions' => Field::advanced(Field::select('Only keywords that are', ['new', 'lost', 'rise', 'fall'], 'Keywords that entered, left, rose in or fell in the top 100.')),
                'sort' => $this->sortField(['po_asc', 'po_desc', 'tg_asc', 'tg_desc', 'tr_asc', 'tr_desc', 'tc_asc', 'tc_desc', 'nq_asc', 'nq_desc']),
                'filter' => $this->filterField(),
                'columns' => $this->columnsField(['Ph', 'Po', 'Pp', 'Pd', 'Ab', 'Nq', 'Cp', 'Tg', 'Tr', 'Tc', 'Co', 'Nr', 'Td', 'Tt', 'Ds', 'Vu', 'Ur', 'Ts', 'Un']),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->seoReport($run, $config, 'subfolder_adwords', 'Ph,Po,Pp,Pd,Nq,Cp,Vu,Tr,Tc,Co,Nr,Td', ['subfolder' => $this->requiredString($config, 'subfolder', 'The subfolder')]);
    }
}
