<?php

namespace App\Nodes\Integrations\Semrush;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SemrushUrlPaidKeywordsNode extends AbstractSemrushReportNode
{
    public function type(): string
    {
        return 'semrush_url_paid_keywords';
    }

    public function name(): string
    {
        return 'Semrush: URL Paid Keywords';
    }

    public function description(): string
    {
        return 'The keywords a URL shows ads for in paid search.';
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
                'limit' => $this->limitField(),
                'offset' => $this->offsetField(),
                'date' => $this->dateField(),
                'sort' => $this->sortField(['po_asc', 'po_desc', 'tg_asc', 'tg_desc', 'tr_asc', 'tr_desc', 'tc_asc', 'tc_desc', 'nq_asc', 'nq_desc']),
                'filter' => $this->filterField(),
                'columns' => $this->columnsField(['Ph', 'Po', 'Nq', 'Cp', 'Co', 'Tg', 'Tr', 'Tc', 'Nr', 'Td', 'Tt', 'Ds', 'Ts']),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->seoReport($run, $config, 'url_adwords', 'Ph,Po,Nq,Cp,Co,Tr,Tc,Nr,Td,Tt,Ds', ['url' => $this->requiredString($config, 'url', 'The url')]);
    }
}
