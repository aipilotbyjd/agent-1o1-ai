<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class FreshdeskGetHelpdeskSettingsNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_get_helpdesk_settings';
    }

    public function name(): string
    {
        return 'Freshdesk: Get Helpdesk Settings';
    }

    public function description(): string
    {
        return 'Reads the helpdesk\'s languages and portal settings.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->freshdesk($run, $config, 'GET', '/settings/helpdesk');
    }
}
