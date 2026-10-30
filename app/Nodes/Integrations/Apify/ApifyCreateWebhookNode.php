<?php

namespace App\Nodes\Integrations\Apify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use InvalidArgumentException;

class ApifyCreateWebhookNode extends AbstractApifyNode
{
    public function type(): string
    {
        return 'apify_create_webhook';
    }

    public function name(): string
    {
        return 'Apify: Create Webhook';
    }

    public function description(): string
    {
        return 'Tells an address when an Actor or task run or build reaches an event. For a flow here, the Run Finished triggers do this for you.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['request_url', 'event_types'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->webhookFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $body = $this->webhookBody($config);

        if (! isset($body['condition'])) {
            throw new InvalidArgumentException('Choose the Actor or task the webhook is for.');
        }

        return ['webhook' => $this->apify($run, $config, 'POST', '/webhooks', $body)];
    }
}
