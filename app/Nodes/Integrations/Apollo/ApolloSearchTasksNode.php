<?php

namespace App\Nodes\Integrations\Apollo;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

class ApolloSearchTasksNode extends AbstractApolloNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'apollo_search_tasks';
    }

    public function name(): string
    {
        return 'Apollo: Search Tasks';
    }

    public function description(): string
    {
        return 'Lists your team\'s tasks. Needs a master API key. As a trigger it starts the flow for each new one.';
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
                'sort_by_field' => Field::advanced(Field::select('Sort by', ['task_due_at' => 'Due date', 'task_priority' => 'Priority'])),
                ...$this->pagingFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->listResult($this->apollo($run, $config, 'POST', '/tasks/search', [...$this->only($config, ['sort_by_field']), ...$this->paging($config)]), 'tasks', $this->shapeTask(...));
    }

    public function triggerName(): string
    {
        return 'Apollo: New task';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a task appears in Apollo. Checks every few minutes.';
    }

    protected function triggerItemsPath(): ?string
    {
        return 'tasks';
    }

    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'limit' => self::MAX_PAGE_SIZE];
    }
}
