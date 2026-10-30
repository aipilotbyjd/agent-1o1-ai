<?php

namespace App\Nodes\Integrations\Perplexity;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class PerplexityCancelAgentResponseNode extends AbstractPerplexityNode
{
    public function type(): string
    {
        return 'perplexity_cancel_agent_response';
    }

    public function name(): string
    {
        return 'Perplexity: Cancel Agent Response';
    }

    public function description(): string
    {
        return 'Cancels a background response.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['response_id'],
            'properties' => [
                ...$this->credentialFields(),
                'response_id' => Field::text('Response id'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $answer = $this->perplexity($run, $config, 'POST', '/v1/agent/'.$this->pathId($config, 'response_id', 'The response id').'/cancel');

        return ['id' => $answer['response_id'] ?? null, 'status' => $answer['status'] ?? null];
    }
}
