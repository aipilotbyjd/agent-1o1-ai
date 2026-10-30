<?php

namespace App\Nodes\Integrations\Semrush;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SemrushKeywordOrganicResultsNode extends AbstractSemrushReportNode
{
    public function type(): string
    {
        return 'semrush_keyword_organic_results';
    }

    public function name(): string
    {
        return 'Semrush: Keyword Organic Results';
    }

    public function description(): string
    {
        return 'The domains ranking in organic search for a keyword.';
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
                'date' => $this->dateField(),
                'columns' => $this->columnsField(['Po', 'Pt', 'Dn', 'Ur', 'Fk', 'Fp', 'Fl']),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->seoReport($run, $config, 'phrase_organic', 'Po,Dn,Ur,Fk,Fp', ['phrase' => $this->requiredString($config, 'phrase', 'The keyword')]);
    }
}
