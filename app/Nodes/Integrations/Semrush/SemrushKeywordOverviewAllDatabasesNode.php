<?php

namespace App\Nodes\Integrations\Semrush;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SemrushKeywordOverviewAllDatabasesNode extends AbstractSemrushReportNode
{
    public function type(): string
    {
        return 'semrush_keyword_overview_all_databases';
    }

    public function name(): string
    {
        return 'Semrush: Keyword Overview (All Databases)';
    }

    public function description(): string
    {
        return 'Volume, cost per click and competition of a keyword in every regional database.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['phrase'],
            'properties' => [
                ...$this->credentialFields(),
                'phrase' => Field::text('Keyword', null, 'seo tools'),
                'database' => Field::advanced($this->databaseField()),
                'columns' => $this->columnsField(['Dt', 'Db', 'Ph', 'Nq', 'Cp', 'Co', 'Nr', 'In', 'Kd']),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->seoReport($run, $config, 'phrase_all', 'Db,Ph,Nq,Cp,Co,Nr,In,Kd', ['phrase' => $this->requiredString($config, 'phrase', 'The keyword')]);
    }
}
