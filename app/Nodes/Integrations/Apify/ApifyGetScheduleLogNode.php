<?php

namespace App\Nodes\Integrations\Apify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ApifyGetScheduleLogNode extends AbstractApifyNode
{
    public function type(): string
    {
        return 'apify_get_schedule_log';
    }

    public function name(): string
    {
        return 'Apify: Get Schedule Log';
    }

    public function description(): string
    {
        return 'Reads when a schedule last ran.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['schedule_id'],
            'properties' => [
                ...$this->credentialFields(),
                'schedule_id' => $this->scheduleField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return ['log' => $this->apify($run, $config, 'GET', '/schedules/'.$this->pathId($config, 'schedule_id', 'The schedule').'/log')];
    }
}
