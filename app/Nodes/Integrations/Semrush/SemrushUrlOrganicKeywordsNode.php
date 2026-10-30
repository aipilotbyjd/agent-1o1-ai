<?php

namespace App\Nodes\Integrations\Semrush;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SemrushUrlOrganicKeywordsNode extends AbstractSemrushReportNode
{
    public function type(): string
    {
        return 'semrush_url_organic_keywords';
    }

    public function name(): string
    {
        return 'Semrush: URL Organic Keywords';
    }

    public function description(): string
    {
        return 'The keywords a URL ranks for in organic search.';
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
                'positions_type' => Field::advanced(Field::select('Position type', ['organic', 'all', 'serp_features'], 'Whether to count SERP feature positions.')),
                'sort' => $this->sortField(['po_asc', 'po_desc', 'tg_asc', 'tg_desc', 'tr_asc', 'tr_desc', 'tc_asc', 'tc_desc', 'nq_asc', 'nq_desc']),
                'filter' => $this->filterField(),
                'columns' => $this->columnsField(['Ph', 'Po', 'Pp', 'Nq', 'Cp', 'Co', 'Kd', 'Tr', 'Tg', 'Tc', 'Nr', 'Td', 'Fp', 'Fk', 'Ts', 'In', 'Pt']),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->seoReport($run, $config, 'url_organic', 'Ph,Po,Nq,Cp,Co,Tr,Tc,Nr,Td,Fp,Ts', ['url' => $this->requiredString($config, 'url', 'The url')]);
    }
}
