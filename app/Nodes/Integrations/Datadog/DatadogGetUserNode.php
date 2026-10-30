<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class DatadogGetUserNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_get_user';
    }

    public function name(): string
    {
        return 'Datadog: Get User';
    }

    public function description(): string
    {
        return 'Gets a user.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
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
        $user = $this->find($run, $config, '/api/v2/users/'.$this->pathId($config, 'user_id', 'The user'));

        return $user === null ? ['found' => false] : ['found' => true, ...$this->flatten((array) ($user['data'] ?? []))];
    }
}
