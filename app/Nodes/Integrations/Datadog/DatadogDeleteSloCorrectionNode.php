<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DatadogDeleteSloCorrectionNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_delete_slo_correction';
    }

    public function name(): string
    {
        return 'Datadog: Delete SLO Correction';
    }

    public function description(): string
    {
        return 'Deletes an SLO correction.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['correction_id'],
            'properties' => [
                ...$this->credentialFields(),
                'correction_id' => Field::text('Correction id'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->pathId($config, 'correction_id', 'The correction id');
        $this->datadog($run, $config, 'DELETE', "/api/v1/slo/correction/{$id}");

        return ['deleted' => true, 'id' => rawurldecode($id)];
    }
}
