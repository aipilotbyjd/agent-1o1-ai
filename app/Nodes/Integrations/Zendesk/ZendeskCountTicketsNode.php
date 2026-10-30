<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ZendeskCountTicketsNode extends AbstractZendeskNode
{
    public function type(): string
    {
        return 'zendesk_count_tickets';
    }

    public function name(): string
    {
        return 'Zendesk: Count Tickets';
    }

    public function description(): string
    {
        return 'Counts the tickets in the account. Large accounts get an approximate number.';
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
        return ['count' => $this->zendesk($run, $config, 'GET', '/tickets/count')['count']['value'] ?? null];
    }
}
