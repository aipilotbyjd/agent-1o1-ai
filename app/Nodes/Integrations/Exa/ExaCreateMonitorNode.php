<?php

namespace App\Nodes\Integrations\Exa;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ExaCreateMonitorNode extends AbstractExaNode
{
    public function type(): string
    {
        return 'exa_create_monitor';
    }

    public function name(): string
    {
        return 'Exa: Create Monitor';
    }

    public function description(): string
    {
        return 'Runs a search on a schedule and posts each run\'s new results to an address. Or start a flow from them with the Monitor Run Completed trigger.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['query', 'webhook_url'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->monitorFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return ['monitor' => $this->exa($run, $config, 'POST', '/monitors', $this->monitorBody($config))];
    }
}
