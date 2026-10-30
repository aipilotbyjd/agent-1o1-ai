<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class DatadogDisableUserNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_disable_user';
    }

    public function name(): string
    {
        return 'Datadog: Disable User';
    }

    public function description(): string
    {
        return 'Disables a user.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['user_id'],
            'properties' => [
                ...$this->credentialFields(),
                'user_id' => $this->userField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->pathId($config, 'user_id', 'The user');
        $this->datadog($run, $config, 'DELETE', "/api/v2/users/{$id}");

        return ['disabled' => true, 'id' => rawurldecode($id)];
    }
}
