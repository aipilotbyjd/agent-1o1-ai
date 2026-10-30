<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DatadogGetSloHistoryNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_get_slo_history';
    }

    public function name(): string
    {
        return 'Datadog: Get SLO History';
    }

    public function description(): string
    {
        return 'Gets the measured service level of an objective over a time window.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['slo_id'],
            'properties' => [
                ...$this->credentialFields(),
                'slo_id' => $this->sloField(),
                'from' => Field::text('From', 'Default now-7d. A date, Unix seconds, or relative.', 'now-7d'),
                'to' => Field::text('To', 'Default now.', 'now'),
                'target' => Field::advanced(Field::text('Target', 'A percentage, to get the error budget against it.')),
                'apply_correction' => Field::advanced(Field::boolean('Apply corrections')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->pathId($config, 'slo_id', 'The SLO');
        $result = $this->datadog($run, $config, 'GET', "/api/v1/slo/{$id}/history", ['from_ts' => $this->unixTime($config['from'] ?? 'now-7d', 'From'), 'to_ts' => $this->unixTime($config['to'] ?? 'now', 'To'), 'target' => $this->text($config, 'target'), 'apply_correction' => ($config['apply_correction'] ?? false) ? 'true' : null]);

        return ['overall' => data_get($result, 'data.overall'), 'thresholds' => data_get($result, 'data.thresholds'), 'groups' => data_get($result, 'data.groups'), 'monitors' => data_get($result, 'data.monitors'), 'errors' => $result['errors'] ?? null];
    }
}
