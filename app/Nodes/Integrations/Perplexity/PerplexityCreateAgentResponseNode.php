<?php

namespace App\Nodes\Integrations\Perplexity;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class PerplexityCreateAgentResponseNode extends AbstractPerplexityNode
{
    public function type(): string
    {
        return 'perplexity_create_agent_response';
    }

    public function name(): string
    {
        return 'Perplexity: Create Agent Response';
    }

    public function description(): string
    {
        return 'Asks the Agent API with your choice of effort, model, tools and answer shape, and waits for the answer.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
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
        return $this->shapeAgentResponse($this->perplexity($run, $config, 'POST', '/v1/agent', $this->agentBody($config), timeout: self::ANSWER_TIMEOUT_SECONDS));
    }
}
