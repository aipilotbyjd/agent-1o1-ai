<?php

namespace App\Nodes\Integrations\Perplexity;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class PerplexityAskNode extends AbstractPerplexityNode
{
    public function type(): string
    {
        return 'perplexity_ask';
    }

    public function name(): string
    {
        return 'Perplexity: Ask';
    }

    public function description(): string
    {
        return 'Answers a question from a live web search, with sources. Quick, for everyday questions.';
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
                ...$this->agentFields(false),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->shapeAgentResponse($this->perplexity($run, $config, 'POST', '/v1/agent', $this->agentBody($config, 'fast'), timeout: self::ANSWER_TIMEOUT_SECONDS));
    }
}
