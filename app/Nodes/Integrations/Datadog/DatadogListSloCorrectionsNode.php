<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DatadogListSloCorrectionsNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_list_slo_corrections';
    }

    public function name(): string
    {
        return 'Datadog: List SLO Corrections';
    }

    public function description(): string
    {
        return 'Lists the corrections of all objectives, or of one.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
                'slo_id' => $this->sloField(),
                'limit' => Field::integer('Max results', 'When no SLO is chosen.', 25, 1, 1000),
                'offset' => Field::advanced(Field::integer('Skip', 'How many to skip, when no SLO is chosen.', 0, 0)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $path = filled($config['slo_id'] ?? null) ? '/api/v1/slo/'.$this->pathId($config, 'slo_id', 'The SLO').'/corrections' : '/api/v1/slo/correction';
        $query = filled($config['slo_id'] ?? null) ? [] : ['limit' => $this->limit($config, 25), 'offset' => (int) ($config['offset'] ?? 0)];
        $corrections = $this->records($this->datadog($run, $config, 'GET', $path, $query));

        return ['corrections' => $corrections, 'count' => count($corrections)];
    }
}
