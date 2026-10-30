<?php

namespace App\Nodes\Integrations\Apify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ApifyDeleteScheduleNode extends AbstractApifyNode
{
    public function type(): string
    {
        return 'apify_delete_schedule';
    }

    public function name(): string
    {
        return 'Apify: Delete Schedule';
    }

    public function description(): string
    {
        return 'Deletes a schedule for good.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
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
        $this->apify($run, $config, 'DELETE', '/schedules/'.$this->pathId($config, 'schedule_id', 'The schedule'));

        return ['deleted' => true];
    }
}
