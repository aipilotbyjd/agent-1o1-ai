<?php

namespace App\Nodes\Integrations\Perplexity;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class PerplexityGetAgentResponseNode extends AbstractPerplexityNode
{
    public function type(): string
    {
        return 'perplexity_get_agent_response';
    }

    public function name(): string
    {
        return 'Perplexity: Get Agent Response';
    }

    public function description(): string
    {
        return 'Reads how a background response is going, and its answer once done.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['response_id'],
            'properties' => [
                ...$this->credentialFields(),
                'response_id' => Field::text('Response id', 'The id a Start node returned.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->shapeAgentResponse($this->perplexity($run, $config, 'GET', '/v1/agent/'.$this->pathId($config, 'response_id', 'The response id')));
    }
}
