<?php

namespace App\Nodes\Integrations\Firecrawl;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class FirecrawlRunMonitorNode extends AbstractFirecrawlNode
{
    public function type(): string
    {
        return 'firecrawl_run_monitor';
    }

    public function name(): string
    {
        return 'Firecrawl: Run Monitor Now';
    }

    public function description(): string
    {
        return 'Starts a check right away, outside the schedule.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['monitor_id'],
            'properties' => [
                ...$this->credentialFields(),
                'monitor_id' => $this->monitorField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $answer = $this->firecrawl($run, $config, 'POST', '/monitor/'.$this->pathId($config, 'monitor_id', 'The monitor').'/run', []);

        return ['check_id' => $answer['id'] ?? data_get($answer, 'data.id'), 'check' => $answer['data'] ?? null];
    }
}
