<?php

namespace App\Nodes\Integrations\Apify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApifyListSchedulesNode extends AbstractApifyNode
{
    public function type(): string
    {
        return 'apify_list_schedules';
    }

    public function name(): string
    {
        return 'Apify: List Schedules';
    }

    public function description(): string
    {
        return 'Lists your schedules.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
                'limit' => Field::integer('Max results', null, 50, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $schedules = $this->listItems($run, $config, '/schedules');

        return ['schedules' => $schedules, 'count' => count($schedules)];
    }
}
