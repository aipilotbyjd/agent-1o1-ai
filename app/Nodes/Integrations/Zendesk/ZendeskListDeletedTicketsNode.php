<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZendeskListDeletedTicketsNode extends AbstractZendeskNode
{
    public function type(): string
    {
        return 'zendesk_list_deleted_tickets';
    }

    public function name(): string
    {
        return 'Zendesk: List Deleted Tickets';
    }

    public function description(): string
    {
        return 'Lists tickets that were deleted and can still be restored.';
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
                'limit' => Field::integer('Max results', null, 100, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->listing($run, $config, '/deleted_tickets', 'deleted_tickets', ['sort_by' => 'deleted_at', 'sort_order' => 'desc']);
    }
}
