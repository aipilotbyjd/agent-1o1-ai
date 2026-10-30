<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DatadogDeleteWebhookNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_delete_webhook';
    }

    public function name(): string
    {
        return 'Datadog: Delete Webhook';
    }

    public function description(): string
    {
        return 'Deletes a webhook configuration.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
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
        $name = $this->pathId($config, 'name', 'The name');
        $this->datadog($run, $config, 'DELETE', "/api/v1/integration/webhooks/configuration/webhooks/{$name}");

        return ['deleted' => true, 'name' => rawurldecode($name)];
    }
}
