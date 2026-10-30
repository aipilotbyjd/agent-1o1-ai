<?php

namespace App\Nodes\Integrations\Exa;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ExaCreateWebhookNode extends AbstractExaNode
{
    public function type(): string
    {
        return 'exa_create_webhook';
    }

    public function name(): string
    {
        return 'Exa: Create Websets Webhook';
    }

    public function description(): string
    {
        return 'Tells an address about Websets events. For a flow here, the Websets triggers do this for you.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['url', 'events'],
            'properties' => [
                ...$this->credentialFields(),
                'url' => Field::text('Address', null, 'https://'),
                'events' => Field::list('Events', 'Such as webset.item.created.', 'webset.item.created'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return ['webhook' => $this->exa($run, $config, 'POST', self::WEBSETS.'/webhooks', ['url' => $this->webUrl($config), 'events' => $this->stringList($config['events'] ?? [])])];
    }
}
