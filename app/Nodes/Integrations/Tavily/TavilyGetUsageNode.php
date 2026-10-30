<?php

namespace App\Nodes\Integrations\Tavily;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class TavilyGetUsageNode extends AbstractTavilyNode
{
    public function type(): string
    {
        return 'tavily_get_usage';
    }

    public function name(): string
    {
        return 'Tavily: Get Usage';
    }

    public function description(): string
    {
        return 'Reads what the key and the account have used, and their limits.';
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
        $answer = $this->tavily($run, $config, 'GET', '/usage');

        return ['key' => $answer['key'] ?? [], 'account' => $answer['account'] ?? []];
    }
}
