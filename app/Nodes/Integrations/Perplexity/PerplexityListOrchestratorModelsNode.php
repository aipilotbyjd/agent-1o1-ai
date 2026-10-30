<?php

namespace App\Nodes\Integrations\Perplexity;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class PerplexityListOrchestratorModelsNode extends AbstractPerplexityNode
{
    public function type(): string
    {
        return 'perplexity_list_orchestrator_models';
    }

    public function name(): string
    {
        return 'Perplexity: List Orchestrator Models';
    }

    public function description(): string
    {
        return 'Lists the models Orchestrator Chat can use.';
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
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $models = array_values((array) ($this->perplexity($run, $config, 'GET', '/orchestrator/v1/models')['data'] ?? []));

        return ['models' => $models, 'count' => count($models)];
    }
}
