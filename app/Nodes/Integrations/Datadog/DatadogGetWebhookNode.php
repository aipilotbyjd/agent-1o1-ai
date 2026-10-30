<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DatadogGetWebhookNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_get_webhook';
    }

    public function name(): string
    {
        return 'Datadog: Get Webhook';
    }

    public function description(): string
    {
        return 'Gets a webhook configuration of the Webhooks integration.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['name'],
            'properties' => [
                ...$this->credentialFields(),
                'name' => Field::text('Name', 'Mentioned in monitors as @webhook-name.', 'my-webhook'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $webhook = $this->find($run, $config, '/api/v1/integration/webhooks/configuration/webhooks/'.$this->pathId($config, 'name', 'The name'));

        return $webhook === null ? ['found' => false] : ['found' => true, ...$webhook];
    }
}
