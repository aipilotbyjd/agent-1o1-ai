<?php

namespace App\Nodes\Integrations\Apify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApifyGetMonthlyUsageNode extends AbstractApifyNode
{
    public function type(): string
    {
        return 'apify_get_monthly_usage';
    }

    public function name(): string
    {
        return 'Apify: Get Monthly Usage';
    }

    public function description(): string
    {
        return 'Reads what the account has used this billing month, or the month with a given date in it.';
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
                'date' => Field::text('Date in the month', 'Leave empty for this month.', '2026-01-15'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return ['usage' => $this->apify($run, $config, 'GET', '/users/me/usage/monthly', query: ['date' => $this->textOf($config, 'date')])];
    }
}
