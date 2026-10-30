<?php

namespace App\Nodes\Integrations\Semrush;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SemrushAddTrackingTagsNode extends AbstractSemrushNode
{
    public function type(): string
    {
        return 'semrush_add_tracking_tags';
    }

    public function name(): string
    {
        return 'Semrush: Add Tracking Tags';
    }

    public function description(): string
    {
        return 'Adds tags to keywords of a Position Tracking campaign.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['campaign_id', 'tags', 'keywords'],
            'properties' => [
                ...$this->credentialFields(),
                'project_id' => $this->projectField(),
                'campaign_id' => $this->campaignField(),
                'tags' => Field::list('Tags', null, 'seo'),
                'keywords' => Field::list('Keywords', null, 'seo tools'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $keywords = $this->requiredList($config, 'keywords', 'The keywords');

        return $this->semrushJson($run, $config, 'PUT', '/management/v1/projects/'.$this->campaignId($config).'/tags', [], array_map(fn (string $tag): array => ['tag' => $tag, 'keywords' => $keywords], $this->requiredList($config, 'tags', 'The tags')));
    }
}
