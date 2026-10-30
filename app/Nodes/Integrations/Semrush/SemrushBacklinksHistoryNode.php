<?php

namespace App\Nodes\Integrations\Semrush;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SemrushBacklinksHistoryNode extends AbstractSemrushReportNode
{
    public function type(): string
    {
        return 'semrush_backlinks_history';
    }

    public function name(): string
    {
        return 'Semrush: Backlinks History';
    }

    public function description(): string
    {
        return 'Month by month backlinks and referring domains.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['target', 'target_type'],
            'properties' => [
                ...$this->credentialFields(),
                'target' => Field::text('Target', 'A root domain, a subdomain or a URL.', 'example.com'),
                'target_type' => $this->targetTypeField(),
                'limit' => $this->limitField(),
                'offset' => $this->offsetField(),
                'columns' => $this->columnsField(['date', 'backlinks_num', 'domains_num', 'score']),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->seoReport($run, $config, 'backlinks_historical', 'date,backlinks_num,domains_num,score', ['target' => $this->requiredString($config, 'target', 'The target')], '/analytics/v1/');
    }
}
