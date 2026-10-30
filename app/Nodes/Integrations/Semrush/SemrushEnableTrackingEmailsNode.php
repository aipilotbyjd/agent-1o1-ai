<?php

namespace App\Nodes\Integrations\Semrush;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class SemrushEnableTrackingEmailsNode extends AbstractSemrushNode
{
    public function type(): string
    {
        return 'semrush_enable_tracking_emails';
    }

    public function name(): string
    {
        return 'Semrush: Turn On Tracking Emails';
    }

    public function description(): string
    {
        return 'Turns on the emails with a campaign\'s statistics.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
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
        $this->semrushJson($run, $config, 'PUT', '/management/v1/projects/'.$this->campaignId($config).'/tracking/notifications');

        return ['enabled' => true];
    }
}
