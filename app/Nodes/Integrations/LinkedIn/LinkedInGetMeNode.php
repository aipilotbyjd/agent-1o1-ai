<?php

namespace App\Nodes\Integrations\LinkedIn;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class LinkedInGetMeNode extends AbstractLinkedInNode
{
    public function type(): string
    {
        return 'linkedin_get_me';
    }

    public function name(): string
    {
        return 'LinkedIn: Get Me';
    }

    public function description(): string
    {
        return 'Reads the member the token belongs to and checks the connection works.';
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
        $me = $this->linkedin($run, $config, 'GET', '/v2/userinfo');

        return ['id' => $me['sub'] ?? null, 'urn' => isset($me['sub']) ? 'urn:li:person:'.$me['sub'] : null, 'name' => $me['name'] ?? null, 'email' => $me['email'] ?? null, 'picture' => $me['picture'] ?? null];
    }
}
