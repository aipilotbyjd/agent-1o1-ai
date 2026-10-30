<?php

namespace App\Nodes\Integrations\Firecrawl;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FirecrawlGetMonitorCheckNode extends AbstractFirecrawlNode
{
    public function type(): string
    {
        return 'firecrawl_get_monitor_check';
    }

    public function name(): string
    {
        return 'Firecrawl: Get Monitor Check';
    }

    public function description(): string
    {
        return 'Reads a check\'s result page by page: which pages stayed the same, changed, appeared or went away.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['monitor_id', 'check_id'],
            'properties' => [
                ...$this->credentialFields(),
                'monitor_id' => $this->monitorField(),
                'check_id' => Field::text('Check id'),
                'page_status' => Field::select('Pages', ['changed', 'new', 'removed', 'same', 'error'], 'Leave empty for all.'),
                'limit' => Field::integer('Max pages', null, 100, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $answer = $this->firecrawl($run, $config, 'GET', '/monitor/'.$this->pathId($config, 'monitor_id', 'The monitor').'/checks/'.$this->pathId($config, 'check_id', 'The check id'), query: ['limit' => $this->limit($config, 100, 1000), 'status' => $this->textOf($config, 'page_status')]);

        return ['check' => $answer['data'] ?? [], 'more_pages' => filled($answer['next'] ?? null)];
    }
}
