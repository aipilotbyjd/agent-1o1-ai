<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DatadogGetSloCorrectionNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_get_slo_correction';
    }

    public function name(): string
    {
        return 'Datadog: Get SLO Correction';
    }

    public function description(): string
    {
        return 'Gets an SLO correction.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
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
        $correction = $this->find($run, $config, '/api/v1/slo/correction/'.$this->pathId($config, 'correction_id', 'The correction id'));

        return $correction === null ? ['found' => false] : ['found' => true, ...$this->flatten((array) ($correction['data'] ?? []))];
    }
}
