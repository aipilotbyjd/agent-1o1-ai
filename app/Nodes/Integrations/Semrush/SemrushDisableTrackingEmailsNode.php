<?php

namespace App\Nodes\Integrations\Semrush;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class SemrushDisableTrackingEmailsNode extends AbstractSemrushNode
{
    public function type(): string
    {
        return 'semrush_disable_tracking_emails';
    }

    public function name(): string
    {
        return 'Semrush: Turn Off Tracking Emails';
    }

    public function description(): string
    {
        return 'Turns off the emails with a campaign\'s statistics.';
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
        $this->semrushJson($run, $config, 'DELETE', '/management/v1/projects/'.$this->campaignId($config).'/tracking/notifications');

        return ['enabled' => false];
    }
}
