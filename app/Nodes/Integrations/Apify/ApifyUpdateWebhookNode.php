<?php

namespace App\Nodes\Integrations\Apify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ApifyUpdateWebhookNode extends AbstractApifyNode
{
    public function type(): string
    {
        return 'apify_update_webhook';
    }

    public function name(): string
    {
        return 'Apify: Update Webhook';
    }

    public function description(): string
    {
        return 'Changes a webhook\'s address, events or template. Empty settings stay as they are.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['webhook_id'],
            'properties' => [
                ...$this->credentialFields(),
                'webhook_id' => $this->webhookField(),
                ...$this->webhookFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return ['webhook' => $this->apify($run, $config, 'PUT', '/webhooks/'.$this->pathId($config, 'webhook_id', 'The webhook'), $this->webhookBody($config))];
    }
}
