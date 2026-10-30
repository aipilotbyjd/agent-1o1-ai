<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class DatadogValidateKeysNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_validate_keys';
    }

    public function name(): string
    {
        return 'Datadog: Get Account Info';
    }

    public function description(): string
    {
        return 'Checks the API and application keys, and says which site the connection is on.';
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
        $data = $this->resolveCredentialData($run, $config);
        $response = self::send($data, 'GET', '/api/v2/validate_keys');

        if ($response->status() === 401 || $response->status() === 403) {
            return ['valid' => false, 'site' => self::siteHost((string) ($data['site'] ?? ''))];
        }

        $this->assertOk($response);

        return ['valid' => true, 'site' => self::siteHost((string) ($data['site'] ?? ''))];
    }
}
