<?php

namespace App\Nodes\Integrations\Semrush;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SemrushKeywordAdsHistoryNode extends AbstractSemrushReportNode
{
    public function type(): string
    {
        return 'semrush_keyword_ads_history';
    }

    public function name(): string
    {
        return 'Semrush: Keyword Ads History';
    }

    public function description(): string
    {
        return 'The domains that have bid on a keyword in the last 12 months, and their positions.';
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
                'columns' => $this->columnsField(['Dn', 'Dt', 'Po', 'Ur', 'Tt', 'Ds', 'Vu', 'At', 'Ac', 'Ad']),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->seoReport($run, $config, 'phrase_adwords_historical', 'Dn,Dt,Po,Ur,Tt,Ds,Vu,At,Ac,Ad', ['phrase' => $this->requiredString($config, 'phrase', 'The keyword')]);
    }
}
