<?php

namespace App\Nodes\Integrations\Semrush;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SemrushKeywordOverviewNode extends AbstractSemrushReportNode
{
    public function type(): string
    {
        return 'semrush_keyword_overview';
    }

    public function name(): string
    {
        return 'Semrush: Keyword Overview';
    }

    public function description(): string
    {
        return 'Volume, cost per click, competition and difficulty of a keyword in one regional database.';
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
                'date' => $this->dateField(),
                'columns' => $this->columnsField(['Ph', 'Nq', 'Cp', 'Co', 'Nr', 'Td', 'In', 'Kd']),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->seoReport($run, $config, 'phrase_this', 'Ph,Nq,Cp,Co,Nr,Td,In,Kd', ['phrase' => $this->requiredString($config, 'phrase', 'The keyword')]);
    }
}
