<?php

namespace App\Nodes\Integrations\Apify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ApifyInvokeScheduleNode extends AbstractApifyNode
{
    public function type(): string
    {
        return 'apify_invoke_schedule';
    }

    public function name(): string
    {
        return 'Apify: Run Schedule Now';
    }

    public function description(): string
    {
        return 'Runs what a schedule runs right away.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
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
        return ['schedule' => $this->apify($run, $config, 'POST', '/schedules/'.$this->pathId($config, 'schedule_id', 'The schedule').'/invoke', [])];
    }
}
