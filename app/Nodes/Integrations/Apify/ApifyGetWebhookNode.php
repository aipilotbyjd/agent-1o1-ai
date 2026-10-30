<?php

namespace App\Nodes\Integrations\Apify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ApifyGetWebhookNode extends AbstractApifyNode
{
    public function type(): string
    {
        return 'apify_get_webhook';
    }

    public function name(): string
    {
        return 'Apify: Get Webhook';
    }

    public function description(): string
    {
        return 'Reads a webhook and when it last fired.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['webhook_id'],
            'properties' => [
                ...$this->credentialFields(),
                'webhook_id' => $this->webhookField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return ['webhook' => $this->apify($run, $config, 'GET', '/webhooks/'.$this->pathId($config, 'webhook_id', 'The webhook'))];
    }
}
