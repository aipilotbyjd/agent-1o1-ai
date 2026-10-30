<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DatadogGetCaseNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_get_case';
    }

    public function name(): string
    {
        return 'Datadog: Get Case';
    }

    public function description(): string
    {
        return 'Gets a work item of Case Management.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['case_id'],
            'properties' => [
                ...$this->credentialFields(),
                'case_id' => Field::text('Case id'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $case = $this->find($run, $config, '/api/v2/cases/'.$this->pathId($config, 'case_id', 'The case id'));

        return $case === null ? ['found' => false] : ['found' => true, ...$this->flatten((array) ($case['data'] ?? []))];
    }
}
