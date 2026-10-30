<?php

namespace App\Nodes\Integrations\Apify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApifyListWebhookDispatchesNode extends AbstractApifyNode
{
    public function type(): string
    {
        return 'apify_list_webhook_dispatches';
    }

    public function name(): string
    {
        return 'Apify: List Webhook Dispatches';
    }

    public function description(): string
    {
        return 'Lists what webhooks have sent, for one webhook or all of them, newest first.';
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
                'webhook_id' => $this->webhookField(),
                'limit' => Field::integer('Max results', null, 50, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $path = filled($config['webhook_id'] ?? null) ? '/webhooks/'.$this->pathId($config, 'webhook_id', 'The webhook').'/dispatches' : '/webhook-dispatches';
        $dispatches = $this->listItems($run, $config, $path);

        return ['dispatches' => $dispatches, 'count' => count($dispatches)];
    }
}
