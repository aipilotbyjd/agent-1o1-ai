<?php

namespace App\Nodes\Integrations\Firecrawl;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

class FirecrawlListAgentRunsNode extends AbstractFirecrawlNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'firecrawl_list_agent_runs';
    }

    public function name(): string
    {
        return 'Firecrawl: List Agent Runs';
    }

    public function description(): string
    {
        return 'Lists the team\'s agent runs, newest first. As a trigger it starts the flow when one finishes. Checked every few minutes.';
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
                'status' => Field::select('Status', ['finished' => 'Finished', 'completed' => 'Completed', 'failed' => 'Failed', 'processing' => 'Processing', 'any' => 'Any'], 'As a trigger, keep this on a finished status.', 'finished'),
                'limit' => Field::integer('Max results', null, 25, 1, 100),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $wanted = match ($config['status'] ?? 'finished') {
            'any' => null,
            'finished', '' => ['completed', 'failed'],
            default => [(string) $config['status']],
        };
        $rows = array_values(array_filter((array) ($this->firecrawl($run, $config, 'GET', '/agent')['agents'] ?? []), fn (mixed $row): bool => is_array($row) && ($wanted === null || in_array($row['status'] ?? null, $wanted, true))));
        $runs = array_map(fn (array $row): array => ['id' => $row['id'] ?? null, 'status' => $row['status'] ?? null, 'prompt' => data_get($row, 'options.prompt'), 'target' => $row['targetHint'] ?? null, 'created_at' => $row['createdAt'] ?? null], array_slice($rows, 0, $this->limit($config, 25, 100)));

        return ['runs' => $runs, 'count' => count($runs)];
    }

    public function triggerName(): string
    {
        return 'Firecrawl: Agent finished';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a Firecrawl agent run finishes. Checked every few minutes.';
    }

    protected function triggerItemsPath(): ?string
    {
        return 'runs';
    }

    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'limit' => 100];
    }

    public function triggerListsNewestFirst(): bool
    {
        return true;
    }
}
