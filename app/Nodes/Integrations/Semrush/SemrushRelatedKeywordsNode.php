<?php

namespace App\Nodes\Integrations\Semrush;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SemrushRelatedKeywordsNode extends AbstractSemrushReportNode
{
    public function type(): string
    {
        return 'semrush_related_keywords';
    }

    public function name(): string
    {
        return 'Semrush: Related Keywords';
    }

    public function description(): string
    {
        return 'Keywords related to a keyword.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['phrase', 'database'],
            'properties' => [
                ...$this->credentialFields(),
                'phrase' => Field::text('Keyword', null, 'seo tools'),
                'database' => $this->databaseField(),
                'limit' => $this->limitField(),
                'offset' => $this->offsetField(),
                'sort' => $this->sortField(['nq_asc', 'nq_desc', 'cp_asc', 'cp_desc', 'co_asc', 'co_desc', 'nr_asc', 'nr_desc', 'rr_asc', 'rr_desc', 'kd_asc', 'kd_desc']),
                'filter' => $this->filterField(),
                'columns' => $this->columnsField(['Ph', 'Nq', 'Cp', 'Co', 'Nr', 'Td', 'Rr', 'Fk', 'In', 'Kd']),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->seoReport($run, $config, 'phrase_related', 'Ph,Nq,Cp,Co,Nr,Td,Rr,In,Kd', ['phrase' => $this->requiredString($config, 'phrase', 'The keyword')]);
    }
}
