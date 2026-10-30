<?php

namespace App\Nodes\Integrations\Firecrawl;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FirecrawlUpdateMonitorNode extends AbstractFirecrawlNode
{
    public function type(): string
    {
        return 'firecrawl_update_monitor';
    }

    public function name(): string
    {
        return 'Firecrawl: Update Monitor';
    }

    public function description(): string
    {
        return 'Changes a monitor\'s name, schedule or pages, or pauses it. Empty settings stay as they are.';
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
                'name' => Field::text('Name'),
                'status' => Field::select('Status', ['active', 'paused'], 'Leave empty to keep it.'),
                ...$this->monitorFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $body = $this->withoutEmpty([...$this->monitorBody($config), 'status' => $this->textOf($config, 'status')]);

        return ['monitor' => $this->firecrawl($run, $config, 'PATCH', '/monitor/'.$this->pathId($config, 'monitor_id', 'The monitor'), $body)['data'] ?? []];
    }
}
