<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DatadogUpdateTeamNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_update_team';
    }

    public function name(): string
    {
        return 'Datadog: Update Team';
    }

    public function description(): string
    {
        return 'Changes a team; the rest stays as it is.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['team_id'],
            'properties' => [
                ...$this->credentialFields(),
                'team_id' => $this->teamField(),
                'name' => Field::text('Name'),
                'handle' => Field::text('Handle'),
                'description' => Field::textarea('Description'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->pathId($config, 'team_id', 'The team');
        $current = (array) data_get($this->datadog($run, $config, 'GET', "/api/v2/team/{$id}"), 'data.attributes', []);
        $attributes = [...array_intersect_key($current, array_flip(['name', 'handle', 'description'])), ...array_filter(['name' => $this->text($config, 'name'), 'handle' => $this->text($config, 'handle'), 'description' => $this->text($config, 'description')], fn (mixed $value): bool => $value !== null)];

        return $this->flatten((array) ($this->datadog($run, $config, 'PATCH', "/api/v2/team/{$id}", [], ['data' => ['type' => 'team', 'attributes' => $attributes]])['data'] ?? []));
    }
}
