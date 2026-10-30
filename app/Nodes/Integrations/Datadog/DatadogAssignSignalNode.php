<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DatadogAssignSignalNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_assign_signal';
    }

    public function name(): string
    {
        return 'Datadog: Assign Security Signal';
    }

    public function description(): string
    {
        return 'Assigns a security signal to a user.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['signal_id', 'user_id'],
            'properties' => [
                ...$this->credentialFields(),
                'signal_id' => Field::text('Signal id', 'From Search Security Signals.'),
                'user_id' => $this->userField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->pathId($config, 'signal_id', 'The signal id');

        return $this->flatten((array) ($this->datadog($run, $config, 'PATCH', "/api/v2/security_monitoring/signals/{$id}/assignee", [], ['data' => ['attributes' => ['assignee' => ['uuid' => $this->requiredString($config, 'user_id', 'The user')]]]])['data'] ?? []));
    }
}
