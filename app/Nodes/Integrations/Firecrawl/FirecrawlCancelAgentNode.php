<?php

namespace App\Nodes\Integrations\Firecrawl;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FirecrawlCancelAgentNode extends AbstractFirecrawlNode
{
    public function type(): string
    {
        return 'firecrawl_cancel_agent';
    }

    public function name(): string
    {
        return 'Firecrawl: Cancel Agent';
    }

    public function description(): string
    {
        return 'Stops a running agent.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
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
        $id = $this->pathId($config, 'agent_id', 'The agent run id');
        $answer = $this->firecrawl($run, $config, 'DELETE', "/agent/{$id}");

        return ['id' => $id, 'cancelled' => (bool) ($answer['success'] ?? true)];
    }
}
