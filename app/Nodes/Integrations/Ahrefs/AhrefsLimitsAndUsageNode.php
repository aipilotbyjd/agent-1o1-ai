<?php

namespace App\Nodes\Integrations\Ahrefs;

use App\Enums\Agents\ActionEffect;

class AhrefsLimitsAndUsageNode extends AbstractAhrefsReportNode
{
    public function type(): string
    {
        return 'ahrefs_limits_and_usage';
    }

    public function name(): string
    {
        return 'Ahrefs: Limits and Usage';
    }

    public function description(): string
    {
        return 'The plan\'s API limits and how much is used. Free.';
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

    protected function endpoint(): array
    {
        return [
            'method' => 'GET',
            'path' => '/subscription-info/limits-and-usage',
            'items' => 'limits_and_usage',
        ];
    }
}
