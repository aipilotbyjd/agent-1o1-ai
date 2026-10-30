<?php

namespace App\Nodes\Integrations\Firecrawl;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FirecrawlGetAgentTraceNode extends AbstractFirecrawlNode
{
    public function type(): string
    {
        return 'firecrawl_get_agent_trace';
    }

    public function name(): string
    {
        return 'Firecrawl: Get Agent Trace';
    }

    public function description(): string
    {
        return 'Lists the steps an agent run took.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['agent_id'],
            'properties' => [
                ...$this->credentialFields(),
                'agent_id' => Field::text('Agent run id'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $answer = $this->firecrawl($run, $config, 'GET', '/agent/'.$this->pathId($config, 'agent_id', 'The agent run id').'/trace');

        return ['id' => $answer['id'] ?? null, 'events' => $answer['events'] ?? [], 'credits_used' => $answer['creditsUsed'] ?? null];
    }
}
