<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ZendeskGetMeNode extends AbstractZendeskNode
{
    public function type(): string
    {
        return 'zendesk_get_me';
    }

    public function name(): string
    {
        return 'Zendesk: Get Current User';
    }

    public function description(): string
    {
        return 'Reads the person this connection acts as.';
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
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->shapeUser((array) ($this->zendesk($run, $config, 'GET', '/users/me')['user'] ?? []));
    }
}
