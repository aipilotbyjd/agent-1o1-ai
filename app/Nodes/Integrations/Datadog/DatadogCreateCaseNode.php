<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DatadogCreateCaseNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_create_case';
    }

    public function name(): string
    {
        return 'Datadog: Create Case';
    }

    public function description(): string
    {
        return 'Creates a work item in Case Management.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['title', 'project_id', 'type_id'],
            'properties' => [
                ...$this->credentialFields(),
                'title' => Field::text('Title'),
                'project_id' => Field::text('Project id'),
                'type_id' => Field::text('Type id', 'A work item type from Case Management.'),
                'description' => Field::textarea('Description'),
                'priority' => Field::select('Priority', ['NOT_DEFINED' => 'Not defined', 'P1' => 'P1', 'P2' => 'P2', 'P3' => 'P3', 'P4' => 'P4', 'P5' => 'P5']),
                'assignee_id' => $this->userField('Assignee'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $attributes = array_filter(['title' => $this->requiredString($config, 'title', 'The title'), 'type_id' => $this->requiredString($config, 'type_id', 'The type id'), 'description' => $this->text($config, 'description'), 'priority' => $this->text($config, 'priority')], fn (mixed $value): bool => $value !== null);
        $data = ['type' => 'case', 'attributes' => $attributes, 'relationships' => ['project' => ['data' => ['id' => $this->requiredString($config, 'project_id', 'The project id'), 'type' => 'project']]]];

        if (filled($config['assignee_id'] ?? null)) {
            $data['relationships']['assignee'] = ['data' => ['id' => (string) $config['assignee_id'], 'type' => 'user']];
        }

        return $this->flatten((array) ($this->datadog($run, $config, 'POST', '/api/v2/cases', [], ['data' => $data])['data'] ?? []));
    }
}
