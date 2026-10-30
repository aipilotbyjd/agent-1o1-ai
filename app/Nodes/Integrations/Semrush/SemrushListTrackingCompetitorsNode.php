<?php

namespace App\Nodes\Integrations\Semrush;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class SemrushListTrackingCompetitorsNode extends AbstractSemrushNode
{
    public function type(): string
    {
        return 'semrush_list_tracking_competitors';
    }

    public function name(): string
    {
        return 'Semrush: List Tracking Competitors';
    }

    public function description(): string
    {
        return 'Lists the competitors of a Position Tracking campaign.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['campaign_id'],
            'properties' => [
                ...$this->credentialFields(),
                'project_id' => $this->projectField(),
                'campaign_id' => $this->campaignField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $answer = $this->semrushJson($run, $config, 'GET', '/management/v1/projects/'.$this->campaignId($config).'/competitors');
        $competitors = array_is_list($answer) ? $answer : (array) ($answer['competitors'] ?? []);

        return ['competitors' => $competitors, 'count' => count($competitors)];
    }
}
