<?php

namespace App\Nodes\Integrations\Semrush;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SemrushBacklinkCompetitorsNode extends AbstractSemrushReportNode
{
    public function type(): string
    {
        return 'semrush_backlink_competitors';
    }

    public function name(): string
    {
        return 'Semrush: Backlink Competitors';
    }

    public function description(): string
    {
        return 'The domains with a similar backlink profile.';
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
                'columns' => $this->columnsField(['score', 'neighbour', 'similarity', 'common_refdomains', 'domains_num', 'backlinks_num']),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->seoReport($run, $config, 'backlinks_competitors', 'score,neighbour,similarity,common_refdomains,domains_num,backlinks_num', ['target' => $this->requiredString($config, 'target', 'The target')], '/analytics/v1/');
    }
}
