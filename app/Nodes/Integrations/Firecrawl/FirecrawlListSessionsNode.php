<?php

namespace App\Nodes\Integrations\Firecrawl;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FirecrawlListSessionsNode extends AbstractFirecrawlNode
{
    public function type(): string
    {
        return 'firecrawl_list_sessions';
    }

    public function name(): string
    {
        return 'Firecrawl: List Browser Sessions';
    }

    public function description(): string
    {
        return 'Lists the team\'s browser sessions.';
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
                'status' => Field::select('Status', ['active', 'destroyed'], 'Leave empty for all.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $sessions = array_values((array) ($this->firecrawl($run, $config, 'GET', '/interact', query: ['status' => $this->textOf($config, 'status')])['sessions'] ?? []));

        return ['sessions' => $sessions, 'count' => count($sessions)];
    }
}
