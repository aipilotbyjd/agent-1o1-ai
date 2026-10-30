<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZendeskGetLatestCommentNode extends AbstractZendeskNode
{
    public function type(): string
    {
        return 'zendesk_get_latest_comment';
    }

    public function name(): string
    {
        return 'Zendesk: Get Latest Comment';
    }

    public function description(): string
    {
        return 'Reads the newest comment of a ticket.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['ticket_id'],
            'properties' => [
                ...$this->credentialFields(),
                'ticket_id' => Field::text('Ticket id'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'ticket_id', 'The ticket');
        $comments = (array) ($this->zendesk($run, $config, 'GET', "/tickets/{$id}/comments", ['sort_order' => 'desc', 'per_page' => 1])['comments'] ?? []);

        return $comments === [] ? ['found' => false] : ['found' => true, ...$this->shapeComment((array) $comments[0])];
    }
}
