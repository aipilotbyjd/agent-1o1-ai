<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DatadogUpdateErrorIssueStateNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_update_error_issue_state';
    }

    public function name(): string
    {
        return 'Datadog: Update Error Tracking Issue State';
    }

    public function description(): string
    {
        return 'Resolves, ignores or reopens an issue of Datadog Error Tracking.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['issue_id', 'state'],
            'properties' => [
                ...$this->credentialFields(),
                'issue_id' => Field::text('Issue id'),
                'state' => Field::select('State', ['OPEN' => 'Open', 'ACKNOWLEDGED' => 'Acknowledged', 'RESOLVED' => 'Resolved', 'IGNORED' => 'Ignored', 'EXCLUDED' => 'Excluded'], null, 'RESOLVED'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->pathId($config, 'issue_id', 'The issue id');

        return $this->flatten((array) ($this->datadog($run, $config, 'PUT', "/api/v2/error-tracking/issues/{$id}/state", [], ['data' => ['type' => 'error_tracking_issue', 'id' => rawurldecode($id), 'attributes' => ['state' => $this->requiredString($config, 'state', 'The state')]]])['data'] ?? []));
    }
}
