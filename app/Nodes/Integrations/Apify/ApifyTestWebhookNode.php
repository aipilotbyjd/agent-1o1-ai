<?php

namespace App\Nodes\Integrations\Apify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ApifyTestWebhookNode extends AbstractApifyNode
{
    public function type(): string
    {
        return 'apify_test_webhook';
    }

    public function name(): string
    {
        return 'Apify: Test Webhook';
    }

    public function description(): string
    {
        return 'Sends a webhook\'s address a test event.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
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
        return ['dispatch' => $this->apify($run, $config, 'POST', '/webhooks/'.$this->pathId($config, 'webhook_id', 'The webhook').'/test', [])];
    }
}
