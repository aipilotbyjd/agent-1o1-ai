<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DatadogGetErrorIssueNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_get_error_issue';
    }

    public function name(): string
    {
        return 'Datadog: Get Error Tracking Issue';
    }

    public function description(): string
    {
        return 'Gets an issue of Datadog Error Tracking.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['issue_id'],
            'properties' => [
                ...$this->credentialFields(),
                'issue_id' => Field::text('Issue id'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $issue = $this->find($run, $config, '/api/v2/error-tracking/issues/'.$this->pathId($config, 'issue_id', 'The issue id'));

        return $issue === null ? ['found' => false] : ['found' => true, ...$this->flatten((array) ($issue['data'] ?? []))];
    }
}
