<?php

namespace App\Nodes\Integrations\Semrush;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SemrushKeywordDifficultyNode extends AbstractSemrushReportNode
{
    public function type(): string
    {
        return 'semrush_keyword_difficulty';
    }

    public function name(): string
    {
        return 'Semrush: Keyword Difficulty';
    }

    public function description(): string
    {
        return 'How hard it is to rank in Google\'s top 10 for up to 100 keywords.';
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
                'phrase' => Field::text('Keywords', 'Up to 100, separated by semicolons.', 'seo;seo tools'),
                'database' => $this->databaseField(),
                'columns' => $this->columnsField(['Ph', 'Kd']),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->seoReport($run, $config, 'phrase_kdi', 'Ph,Kd', ['phrase' => $this->requiredString($config, 'phrase', 'The keywords')]);
    }
}
