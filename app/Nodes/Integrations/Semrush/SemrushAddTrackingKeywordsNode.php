<?php

namespace App\Nodes\Integrations\Semrush;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SemrushAddTrackingKeywordsNode extends AbstractSemrushNode
{
    public function type(): string
    {
        return 'semrush_add_tracking_keywords';
    }

    public function name(): string
    {
        return 'Semrush: Add Tracking Keywords';
    }

    public function description(): string
    {
        return 'Adds keywords to a Position Tracking campaign, with tags if given.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
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
                'tags' => Field::list('Tags', 'Put on every keyword added.', 'seo'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $tags = $this->listOf($config, 'tags');
        $keywords = array_map(fn (string $keyword): array => $tags !== [] ? ['keyword' => $keyword, 'tags' => $tags] : ['keyword' => $keyword], $this->requiredList($config, 'keywords', 'The keywords'));

        return $this->semrushJson($run, $config, 'PUT', '/management/v1/projects/'.$this->campaignId($config).'/keywords', [], ['keywords' => $keywords]);
    }
}
