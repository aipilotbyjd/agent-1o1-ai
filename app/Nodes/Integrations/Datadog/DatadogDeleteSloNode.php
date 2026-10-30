<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DatadogDeleteSloNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_delete_slo';
    }

    public function name(): string
    {
        return 'Datadog: Delete SLO';
    }

    public function description(): string
    {
        return 'Deletes a service level objective.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['slo_id'],
            'properties' => [
                ...$this->credentialFields(),
                'slo_id' => $this->sloField(),
                'force' => Field::advanced(Field::boolean('Delete even if a dashboard or monitor uses it')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->pathId($config, 'slo_id', 'The SLO');
        $this->datadog($run, $config, 'DELETE', "/api/v1/slo/{$id}", ['force' => ($config['force'] ?? false) ? 'true' : null]);

        return ['deleted' => true, 'id' => rawurldecode($id)];
    }
}
