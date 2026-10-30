<?php

namespace App\Nodes\Integrations\Apify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class ApifyUpdateLimitsNode extends AbstractApifyNode
{
    public function type(): string
    {
        return 'apify_update_limits';
    }

    public function name(): string
    {
        return 'Apify: Update Limits';
    }

    public function description(): string
    {
        return 'Sets the account\'s monthly spending limit and data retention.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
                'max_monthly_usage_usd' => Field::integer('Max monthly usage (USD)', null, null, 0),
                'data_retention_days' => Field::integer('Keep unnamed storage (days)', null, null, 1),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $body = $this->withoutEmpty(['maxMonthlyUsageUsd' => $this->floatOf($config, 'max_monthly_usage_usd'), 'dataRetentionDays' => $this->intOf($config, 'data_retention_days')]);

        if ($body === []) {
            throw new InvalidArgumentException('Set a limit to change.');
        }

        $this->apify($run, $config, 'PUT', '/users/me/limits', $body);

        return ['updated' => true];
    }
}
