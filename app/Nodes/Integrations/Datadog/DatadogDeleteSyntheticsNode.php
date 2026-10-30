<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class DatadogDeleteSyntheticsNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_delete_synthetics';
    }

    public function name(): string
    {
        return 'Datadog: Delete Synthetic Tests';
    }

    public function description(): string
    {
        return 'Deletes Synthetic tests.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['public_ids'],
            'properties' => [
                ...$this->credentialFields(),
                'public_ids' => Field::list('Test ids', 'Public ids, like abc-def-ghi.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $ids = $this->listOf($config, 'public_ids') ?: throw new InvalidArgumentException('At least one test id is required.');
        $result = $this->datadog($run, $config, 'POST', '/api/v1/synthetics/tests/delete', [], ['public_ids' => $ids]);

        return ['deleted' => array_values((array) ($result['deleted_tests'] ?? []))];
    }
}
