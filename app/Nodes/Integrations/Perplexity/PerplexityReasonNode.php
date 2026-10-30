<?php

namespace App\Nodes\Integrations\Perplexity;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class PerplexityReasonNode extends AbstractPerplexityNode
{
    public function type(): string
    {
        return 'perplexity_reason';
    }

    public function name(): string
    {
        return 'Perplexity: Reason';
    }

    public function description(): string
    {
        return 'Works through a hard question step by step, with sources. Can take up to a minute and a half: beyond that, use Start Research.';
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
        return $this->shapeAgentResponse($this->perplexity($run, $config, 'POST', '/v1/agent', $this->agentBody($config, 'medium'), timeout: self::ANSWER_TIMEOUT_SECONDS));
    }
}
