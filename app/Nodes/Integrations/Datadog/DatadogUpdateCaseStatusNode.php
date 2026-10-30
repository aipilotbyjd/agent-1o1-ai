<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DatadogUpdateCaseStatusNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_update_case_status';
    }

    public function name(): string
    {
        return 'Datadog: Update Case Status';
    }

    public function description(): string
    {
        return 'Changes the status of a work item in Case Management.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['case_id', 'status'],
            'properties' => [
                ...$this->credentialFields(),
                'case_id' => Field::text('Case id'),
                'status' => Field::select('Status', ['OPEN' => 'Open', 'IN_PROGRESS' => 'In progress', 'CLOSED' => 'Closed'], null, 'IN_PROGRESS'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->pathId($config, 'case_id', 'The case id');

        return $this->flatten((array) ($this->datadog($run, $config, 'POST', "/api/v2/cases/{$id}/status", [], ['data' => ['type' => 'case', 'attributes' => ['status' => $this->requiredString($config, 'status', 'The status')]]])['data'] ?? []));
    }
}
