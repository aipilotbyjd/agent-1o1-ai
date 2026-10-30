<?php

namespace App\Nodes\Integrations\Exa;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ExaDeleteWebhookNode extends AbstractExaNode
{
    public function type(): string
    {
        return 'exa_delete_webhook';
    }

    public function name(): string
    {
        return 'Exa: Delete Websets Webhook';
    }

    public function description(): string
    {
        return 'Deletes a Websets webhook.';
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
                'webhook_id' => Field::text('Webhook id'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $this->exa($run, $config, 'DELETE', self::WEBSETS.'/webhooks/'.$this->pathId($config, 'webhook_id', 'The webhook id'));

        return ['deleted' => true];
    }
}
