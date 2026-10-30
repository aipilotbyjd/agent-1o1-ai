<?php

namespace App\Nodes\Integrations\Semrush;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SemrushRemoveTrackingCompetitorsNode extends AbstractSemrushNode
{
    public function type(): string
    {
        return 'semrush_remove_tracking_competitors';
    }

    public function name(): string
    {
        return 'Semrush: Remove Tracking Competitors';
    }

    public function description(): string
    {
        return 'Removes competitors from a Position Tracking campaign.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['campaign_id', 'competitors'],
            'properties' => [
                ...$this->credentialFields(),
                'project_id' => $this->projectField(),
                'campaign_id' => $this->campaignField(),
                'competitors' => Field::list('Competitors', 'Domains.', 'example.com'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->semrushJson($run, $config, 'DELETE', '/management/v1/projects/'.$this->campaignId($config).'/competitors', [], ['competitors' => array_map(self::bareDomain(...), $this->requiredList($config, 'competitors', 'The competitors'))]);
    }
}
