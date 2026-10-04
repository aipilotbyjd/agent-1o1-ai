<?php

namespace App\Nodes\Integrations\Outlook;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class OutlookGetMessageNode extends AbstractOutlookNode
{
    public function type(): string
    {
        return 'outlook_get_message';
    }

    public function name(): string
    {
        return 'Outlook: Get Message';
    }

    public function description(): string
    {
        return 'Gets one message, with its plain-text body.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['message_id'],
            'properties' => [
                'access_token' => ['type' => 'string'],
                'credential_id' => ['type' => 'string'],
                'message_id' => ['type' => 'string'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->get($run, '/me/messages/'.rawurlencode((string) $config['message_id']), $config);
    }
}
