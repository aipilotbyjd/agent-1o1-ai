<?php

namespace App\Nodes\Integrations\Semrush;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SemrushAddTrackingCompetitorsNode extends AbstractSemrushNode
{
    public function type(): string
    {
        return 'semrush_add_tracking_competitors';
    }

    public function name(): string
    {
        return 'Semrush: Add Tracking Competitors';
    }

    public function description(): string
    {
        return 'Adds up to 20 competitors to a Position Tracking campaign.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
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
        return $this->semrushJson($run, $config, 'PUT', '/management/v1/projects/'.$this->campaignId($config).'/competitors', [], ['competitors' => array_map(self::bareDomain(...), $this->requiredList($config, 'competitors', 'The competitors'))]);
    }
}
