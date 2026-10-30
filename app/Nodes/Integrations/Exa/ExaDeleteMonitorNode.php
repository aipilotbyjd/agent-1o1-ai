<?php

namespace App\Nodes\Integrations\Exa;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ExaDeleteMonitorNode extends AbstractExaNode
{
    public function type(): string
    {
        return 'exa_delete_monitor';
    }

    public function name(): string
    {
        return 'Exa: Delete Monitor';
    }

    public function description(): string
    {
        return 'Deletes a search monitor for good.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['monitor_id'],
            'properties' => [
                ...$this->credentialFields(),
                'monitor_id' => $this->monitorField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $this->exa($run, $config, 'DELETE', '/monitors/'.$this->pathId($config, 'monitor_id', 'The monitor'));

        return ['deleted' => true];
    }
}
