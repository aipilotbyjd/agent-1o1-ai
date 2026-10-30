<?php

namespace App\Nodes\Integrations\Apify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApifyUpdateScheduleNode extends AbstractApifyNode
{
    public function type(): string
    {
        return 'apify_update_schedule';
    }

    public function name(): string
    {
        return 'Apify: Update Schedule';
    }

    public function description(): string
    {
        return 'Changes when a schedule runs and what it runs, or switches it on or off. Empty settings stay as they are.';
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
                'name' => Field::text('Name'),
                ...$this->scheduleFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return ['schedule' => $this->apify($run, $config, 'PUT', '/schedules/'.$this->pathId($config, 'schedule_id', 'The schedule'), $this->scheduleBody($config))];
    }
}
