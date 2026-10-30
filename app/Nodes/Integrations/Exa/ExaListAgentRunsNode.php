<?php

namespace App\Nodes\Integrations\Exa;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

class ExaListAgentRunsNode extends AbstractExaNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'exa_list_agent_runs';
    }

    public function name(): string
    {
        return 'Exa: List Research Runs';
    }

    public function description(): string
    {
        return 'Lists the team\'s research runs, newest first. As a trigger it starts the flow when one finishes. Checked every few minutes.';
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
                'only_finished' => Field::boolean('Only finished runs', 'As a trigger, leave this on.', true),
                ...$this->pageFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $page = $this->shapeList($this->exa($run, $config, 'GET', '/agent/runs', query: $this->pageQuery($config)));
        $runs = array_map($this->shapeAgentRun(...), $page['items']);

        if ($this->boolOf($config, 'only_finished') ?? true) {
            $runs = array_values(array_filter($runs, fn (array $item): bool => $item['finished']));
        }

        return ['runs' => $runs, 'count' => count($runs), 'next_cursor' => $page['next_cursor']];
    }

    public function triggerName(): string
    {
        return 'Exa: Research finished';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when an Exa research run finishes. Checked every few minutes.';
    }

    protected function triggerItemsPath(): ?string
    {
        return 'runs';
    }

    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'limit' => 100, 'only_finished' => true];
    }

    public function triggerListsNewestFirst(): bool
    {
        return true;
    }
}
