<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DatadogCreateTeamNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_create_team';
    }

    public function name(): string
    {
        return 'Datadog: Create Team';
    }

    public function description(): string
    {
        return 'Creates a team.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['name', 'handle'],
            'properties' => [
                ...$this->credentialFields(),
                'name' => Field::text('Name'),
                'handle' => Field::text('Handle', 'Lower case letters, digits and -.', 'payments'),
                'description' => Field::textarea('Description'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $attributes = array_filter(['name' => $this->requiredString($config, 'name', 'The name'), 'handle' => $this->requiredString($config, 'handle', 'The handle'), 'description' => $this->text($config, 'description')], fn (mixed $value): bool => $value !== null);

        return $this->flatten((array) ($this->datadog($run, $config, 'POST', '/api/v2/team', [], ['data' => ['type' => 'team', 'attributes' => $attributes]])['data'] ?? []));
    }
}
