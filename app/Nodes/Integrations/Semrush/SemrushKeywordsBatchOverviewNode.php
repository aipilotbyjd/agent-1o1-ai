<?php

namespace App\Nodes\Integrations\Semrush;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SemrushKeywordsBatchOverviewNode extends AbstractSemrushReportNode
{
    public function type(): string
    {
        return 'semrush_keywords_batch_overview';
    }

    public function name(): string
    {
        return 'Semrush: Keywords Batch Overview';
    }

    public function description(): string
    {
        return 'Volume, cost per click, competition and difficulty of up to 100 keywords.';
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
                'date' => $this->dateField(),
                'columns' => $this->columnsField(['Ph', 'Nq', 'Cp', 'Co', 'Nr', 'Td', 'In', 'Kd']),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->seoReport($run, $config, 'phrase_these', 'Ph,Nq,Cp,Co,Nr,Td,In,Kd', ['phrase' => $this->requiredString($config, 'phrase', 'The keywords')]);
    }
}
