<?php

namespace App\Nodes\Integrations\Perplexity;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class PerplexityStartAgentResponseNode extends AbstractPerplexityNode
{
    public function type(): string
    {
        return 'perplexity_start_agent_response';
    }

    public function name(): string
    {
        return 'Perplexity: Start Agent Response';
    }

    public function description(): string
    {
        return 'Sends the Agent API a request to run in the background and returns the id at once. Read the answer with Get Agent Response.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['prompt'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->agentFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->shapeAgentResponse($this->perplexity($run, $config, 'POST', '/v1/agent', $this->agentBody($config, background: true)));
    }
}
