<?php

namespace App\Nodes\Integrations\Apollo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ApolloListEmailSchedulesNode extends AbstractApolloNode
{
    public function type(): string
    {
        return 'apollo_list_email_schedules';
    }

    public function name(): string
    {
        return 'Apollo: List Email Schedules';
    }

    public function description(): string
    {
        return 'Lists the sending schedules a sequence can use.';
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
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->listResult($this->apollo($run, $config, 'GET', '/emailer_schedules'), 'emailer_schedules');
    }
}
