<?php

namespace App\Nodes\Integrations\Apify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApifyGetWebhookDispatchNode extends AbstractApifyNode
{
    public function type(): string
    {
        return 'apify_get_webhook_dispatch';
    }

    public function name(): string
    {
        return 'Apify: Get Webhook Dispatch';
    }

    public function description(): string
    {
        return 'Reads one sending of a webhook, with the address\'s answers.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['dispatch_id'],
            'properties' => [
                ...$this->credentialFields(),
                'dispatch_id' => Field::text('Dispatch id'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return ['dispatch' => $this->apify($run, $config, 'GET', '/webhook-dispatches/'.$this->pathId($config, 'dispatch_id', 'The dispatch id'))];
    }
}
