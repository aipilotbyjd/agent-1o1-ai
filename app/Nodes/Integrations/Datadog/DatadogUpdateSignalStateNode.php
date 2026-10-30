<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DatadogUpdateSignalStateNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_update_signal_state';
    }

    public function name(): string
    {
        return 'Datadog: Update Security Signal State';
    }

    public function description(): string
    {
        return 'Opens, archives or marks a security signal under review.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['signal_id', 'state'],
            'properties' => [
                ...$this->credentialFields(),
                'signal_id' => Field::text('Signal id', 'From Search Security Signals.'),
                'state' => Field::select('State', ['open' => 'Open', 'under_review' => 'Under review', 'archived' => 'Archived'], null, 'open'),
                'archive_reason' => Field::select('Archive reason', ['none' => 'None', 'false_positive' => 'False positive', 'testing_or_maintenance' => 'Testing or maintenance', 'remediated' => 'Remediated', 'investigated_case_opened' => 'Investigated, case opened', 'true_positive_benign' => 'True positive, benign', 'true_positive_malicious' => 'True positive, malicious', 'other' => 'Other']),
                'archive_comment' => Field::textarea('Archive comment'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->pathId($config, 'signal_id', 'The signal id');
        $attributes = array_filter(['state' => $this->requiredString($config, 'state', 'The state'), 'archive_reason' => $this->text($config, 'archive_reason'), 'archive_comment' => $this->text($config, 'archive_comment')], fn (mixed $value): bool => $value !== null);

        return $this->flatten((array) ($this->datadog($run, $config, 'PATCH', "/api/v2/security_monitoring/signals/{$id}/state", [], ['data' => ['type' => 'signal_metadata', 'attributes' => $attributes]])['data'] ?? []));
    }
}
