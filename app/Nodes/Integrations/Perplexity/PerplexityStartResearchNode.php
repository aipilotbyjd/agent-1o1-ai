<?php

namespace App\Nodes\Integrations\Perplexity;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class PerplexityStartResearchNode extends AbstractPerplexityNode
{
    public function type(): string
    {
        return 'perplexity_start_research';
    }

    public function name(): string
    {
        return 'Perplexity: Start Research';
    }

    public function description(): string
    {
        return 'Starts deep research that takes minutes and returns the id at once. Read the report with Get Agent Response.';
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
                ...$this->agentFields(false),
                'effort' => Field::select('Effort', ['high', 'xhigh'], null, 'high'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->shapeAgentResponse($this->perplexity($run, $config, 'POST', '/v1/agent', $this->agentBody($config, $this->textOf($config, 'effort') ?? 'high', true)));
    }
}
