<?php

namespace App\Nodes\Integrations\Apify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ApifyGetScheduleNode extends AbstractApifyNode
{
    public function type(): string
    {
        return 'apify_get_schedule';
    }

    public function name(): string
    {
        return 'Apify: Get Schedule';
    }

    public function description(): string
    {
        return 'Reads a schedule: when it runs, and what.';
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
        return ['schedule' => $this->apify($run, $config, 'GET', '/schedules/'.$this->pathId($config, 'schedule_id', 'The schedule'))];
    }
}
