<?php

namespace App\Nodes\Integrations\Exa;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;

/**
 * A search monitor posts to one address of its own, set when it is made, so this trigger reads its runs instead of taking that over.
 */
class ExaListMonitorRunsNode extends AbstractExaNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'exa_list_monitor_runs';
    }

    public function name(): string
    {
        return 'Exa: List Monitor Runs';
    }

    public function description(): string
    {
        return 'Lists a search monitor\'s runs, newest first, with what they found. As a trigger it starts the flow when a run completes. Checked every few minutes.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['monitor_id'],
            'properties' => [
                ...$this->credentialFields(),
                'monitor_id' => $this->monitorField(),
                ...$this->pageFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $page = $this->shapeList($this->exa($run, $config, 'GET', '/monitors/'.$this->pathId($config, 'monitor_id', 'The monitor').'/runs', query: $this->pageQuery($config)));

        return ['runs' => $page['items'], 'count' => $page['count'], 'next_cursor' => $page['next_cursor']];
    }

    public function triggerName(): string
    {
        return 'Exa: Monitor run completed';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a run of an Exa search monitor completes. Checked every few minutes.';
    }

    protected function triggerItemsPath(): ?string
    {
        return 'runs';
    }

    public function triggerItems(array $output, array $config): array
    {
        return array_values(array_filter((array) ($output['runs'] ?? []), fn (mixed $item): bool => is_array($item) && ($item['status'] ?? null) === 'completed'));
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
