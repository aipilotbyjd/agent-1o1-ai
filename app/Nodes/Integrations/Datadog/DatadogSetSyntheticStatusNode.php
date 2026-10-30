<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DatadogSetSyntheticStatusNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_set_synthetic_status';
    }

    public function name(): string
    {
        return 'Datadog: Pause Or Start Synthetic Test';
    }

    public function description(): string
    {
        return 'Pauses a Synthetic test, or starts it again.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['public_id', 'status'],
            'properties' => [
                ...$this->credentialFields(),
                'public_id' => $this->syntheticField(),
                'status' => Field::select('Status', ['live' => 'Live', 'paused' => 'Paused'], null, 'live'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->pathId($config, 'public_id', 'The test');
        $status = $this->text($config, 'status') ?? 'live';
        $this->datadog($run, $config, 'PUT', "/api/v1/synthetics/tests/{$id}/status", [], ['new_status' => $status]);

        return ['id' => rawurldecode($id), 'status' => $status];
    }
}
