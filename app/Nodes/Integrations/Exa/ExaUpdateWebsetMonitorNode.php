<?php

namespace App\Nodes\Integrations\Exa;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ExaUpdateWebsetMonitorNode extends AbstractExaNode
{
    public function type(): string
    {
        return 'exa_update_webset_monitor';
    }

    public function name(): string
    {
        return 'Exa: Update Webset Monitor';
    }

    public function description(): string
    {
        return 'Switches a webset monitor on or off, or changes its schedule.';
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
                'monitor_id' => Field::text('Monitor id'),
                'status' => Field::select('Status', ['enabled', 'disabled'], 'Leave empty to keep it.'),
                'cron' => Field::text('When (cron)'),
                'timezone' => Field::advanced(Field::text('Time zone')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return ['monitor' => $this->exa($run, $config, 'PATCH', self::WEBSETS.'/monitors/'.$this->pathId($config, 'monitor_id', 'The monitor id'), $this->withoutEmpty(['status' => $this->textOf($config, 'status'), 'cadence' => $this->withoutEmpty(['cron' => $this->textOf($config, 'cron'), 'timezone' => $this->textOf($config, 'timezone')])]))];
    }
}
