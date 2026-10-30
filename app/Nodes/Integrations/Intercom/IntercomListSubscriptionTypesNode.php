<?php

namespace App\Nodes\Integrations\Intercom;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class IntercomListSubscriptionTypesNode extends AbstractIntercomNode
{
    public function type(): string
    {
        return 'intercom_list_subscription_types';
    }

    public function name(): string
    {
        return 'Intercom: List Subscription Types';
    }

    public function description(): string
    {
        return 'Lists the subscription types contacts can opt in or out of.';
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
        $rows = (array) ($this->intercom($run, $config, 'GET', '/subscription_types')['data'] ?? []);

        return ['subscription_types' => $rows, 'count' => count($rows)];
    }
}
