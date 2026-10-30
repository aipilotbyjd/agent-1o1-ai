<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ZendeskCountOrganizationsNode extends AbstractZendeskNode
{
    public function type(): string
    {
        return 'zendesk_count_organizations';
    }

    public function name(): string
    {
        return 'Zendesk: Count Organizations';
    }

    public function description(): string
    {
        return 'Counts the organizations in the account.';
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
        return ['count' => $this->zendesk($run, $config, 'GET', '/organizations/count')['count']['value'] ?? null];
    }
}
