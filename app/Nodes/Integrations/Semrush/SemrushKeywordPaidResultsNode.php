<?php

namespace App\Nodes\Integrations\Semrush;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SemrushKeywordPaidResultsNode extends AbstractSemrushReportNode
{
    public function type(): string
    {
        return 'semrush_keyword_paid_results';
    }

    public function name(): string
    {
        return 'Semrush: Keyword Paid Results';
    }

    public function description(): string
    {
        return 'The domains showing ads in paid search for a keyword.';
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
                'columns' => $this->columnsField(['Dn', 'Ur', 'Vu']),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->seoReport($run, $config, 'phrase_adwords', 'Dn,Ur,Vu', ['phrase' => $this->requiredString($config, 'phrase', 'The keyword')]);
    }
}
