<?php

namespace App\Nodes\Integrations\Exa;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ExaGetWebsetMonitorNode extends AbstractExaNode
{
    public function type(): string
    {
        return 'exa_get_webset_monitor';
    }

    public function name(): string
    {
        return 'Exa: Get Webset Monitor';
    }

    public function description(): string
    {
        return 'Reads a webset monitor.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['monitor_id'],
            'properties' => [
                ...$this->credentialFields(),
                'monitor_id' => Field::text('Monitor id'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return ['monitor' => $this->exa($run, $config, 'GET', self::WEBSETS.'/monitors/'.$this->pathId($config, 'monitor_id', 'The monitor id'))];
    }
}
