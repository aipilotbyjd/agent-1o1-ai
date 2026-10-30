<?php

namespace App\Nodes\Integrations\Exa;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ExaUpdateMonitorNode extends AbstractExaNode
{
    public function type(): string
    {
        return 'exa_update_monitor';
    }

    public function name(): string
    {
        return 'Exa: Update Monitor';
    }

    public function description(): string
    {
        return 'Changes a search monitor, or pauses it. Empty settings stay as they are.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['monitor_id'],
            'properties' => [
                ...$this->credentialFields(),
                'monitor_id' => $this->monitorField(),
                'status' => Field::select('Status', ['active', 'paused'], 'Leave empty to keep it.'),
                ...$this->monitorFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return ['monitor' => $this->exa($run, $config, 'PATCH', '/monitors/'.$this->pathId($config, 'monitor_id', 'The monitor'), $this->withoutEmpty([...$this->monitorBody($config), 'status' => $this->textOf($config, 'status')]))];
    }
}
