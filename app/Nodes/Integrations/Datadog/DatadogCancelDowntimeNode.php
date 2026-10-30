<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DatadogCancelDowntimeNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_cancel_downtime';
    }

    public function name(): string
    {
        return 'Datadog: Cancel Downtime';
    }

    public function description(): string
    {
        return 'Cancels a downtime so monitors notify again.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['downtime_id'],
            'properties' => [
                ...$this->credentialFields(),
                'downtime_id' => Field::text('Downtime id', 'From List Downtimes.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->pathId($config, 'downtime_id', 'The downtime id');
        $this->datadog($run, $config, 'DELETE', "/api/v2/downtime/{$id}");

        return ['cancelled' => true, 'id' => rawurldecode($id)];
    }
}
