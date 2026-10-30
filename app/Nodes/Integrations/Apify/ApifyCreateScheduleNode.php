<?php

namespace App\Nodes\Integrations\Apify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApifyCreateScheduleNode extends AbstractApifyNode
{
    public function type(): string
    {
        return 'apify_create_schedule';
    }

    public function name(): string
    {
        return 'Apify: Create Schedule';
    }

    public function description(): string
    {
        return 'Runs Actors and tasks on a cron schedule.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['name', 'cron', 'actions'],
            'properties' => [
                ...$this->credentialFields(),
                'name' => Field::text('Name', 'Lower case letters, digits and -.'),
                ...$this->scheduleFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return ['schedule' => $this->apify($run, $config, 'POST', '/schedules', $this->scheduleBody($config))];
    }
}
