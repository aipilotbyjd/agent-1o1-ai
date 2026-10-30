<?php

namespace App\Nodes\Integrations\Semrush;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class SemrushListTrackingCampaignsNode extends AbstractSemrushNode
{
    public function type(): string
    {
        return 'semrush_list_tracking_campaigns';
    }

    public function name(): string
    {
        return 'Semrush: List Tracking Campaigns';
    }

    public function description(): string
    {
        return 'Lists the Position Tracking campaigns of a project.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['project_id'],
            'properties' => [
                ...$this->credentialFields(),
                'project_id' => $this->projectField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $answer = $this->semrushJson($run, $config, 'GET', '/management/v1/projects/'.$this->projectId($config).'/tracking/campaigns');
        $campaigns = array_is_list($answer) ? $answer : (array) ($answer['campaigns'] ?? $answer['data'] ?? []);

        return ['campaigns' => $campaigns, 'count' => count($campaigns)];
    }
}
