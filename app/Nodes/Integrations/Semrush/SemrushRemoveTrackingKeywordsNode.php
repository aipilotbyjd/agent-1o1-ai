<?php

namespace App\Nodes\Integrations\Semrush;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SemrushRemoveTrackingKeywordsNode extends AbstractSemrushNode
{
    public function type(): string
    {
        return 'semrush_remove_tracking_keywords';
    }

    public function name(): string
    {
        return 'Semrush: Remove Tracking Keywords';
    }

    public function description(): string
    {
        return 'Removes keywords from a Position Tracking campaign.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['campaign_id', 'keywords'],
            'properties' => [
                ...$this->credentialFields(),
                'project_id' => $this->projectField(),
                'campaign_id' => $this->campaignField(),
                'keywords' => Field::list('Keywords', null, 'seo tools'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->semrushJson($run, $config, 'DELETE', '/management/v1/projects/'.$this->campaignId($config).'/keywords', [], ['keywords' => array_map(fn (string $keyword): array => ['keyword' => $keyword], $this->requiredList($config, 'keywords', 'The keywords'))]);
    }
}
