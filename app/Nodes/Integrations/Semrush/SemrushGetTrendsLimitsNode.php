<?php

namespace App\Nodes\Integrations\Semrush;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class SemrushGetTrendsLimitsNode extends AbstractSemrushNode
{
    public function type(): string
    {
        return 'semrush_get_trends_limits';
    }

    public function name(): string
    {
        return 'Semrush: Get Trends API Usage';
    }

    public function description(): string
    {
        return 'Reads the month\'s Trends API usage. Free.';
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
        return $this->semrushJson($run, $config, 'GET', '/analytics/ta/limits/key/'.rawurlencode($this->resolveAccessToken($run, $config)));
    }
}
