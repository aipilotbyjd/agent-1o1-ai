<?php

namespace App\Nodes\Integrations\Apify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ApifyDeleteWebhookNode extends AbstractApifyNode
{
    public function type(): string
    {
        return 'apify_delete_webhook';
    }

    public function name(): string
    {
        return 'Apify: Delete Webhook';
    }

    public function description(): string
    {
        return 'Deletes a webhook for good.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
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
        $this->apify($run, $config, 'DELETE', '/webhooks/'.$this->pathId($config, 'webhook_id', 'The webhook'));

        return ['deleted' => true];
    }
}
