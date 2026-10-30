<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZendeskCountViewTicketsNode extends AbstractZendeskNode
{
    public function type(): string
    {
        return 'zendesk_count_view_tickets';
    }

    public function name(): string
    {
        return 'Zendesk: Count Tickets In View';
    }

    public function description(): string
    {
        return 'Counts the tickets in a view.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['view_id'],
            'properties' => [
                ...$this->credentialFields(),
                'view_id' => Field::dynamic('View', 'zendesk.views'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'view_id', 'The view');
        $count = (array) ($this->zendesk($run, $config, 'GET', "/views/{$id}/count")['view_count'] ?? []);

        return ['view_id' => (int) $id, 'count' => $count['value'] ?? null, 'fresh' => $count['fresh'] ?? null];
    }
}
