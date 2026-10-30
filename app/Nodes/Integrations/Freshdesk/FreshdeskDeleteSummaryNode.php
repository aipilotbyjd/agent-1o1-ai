<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskDeleteSummaryNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_delete_summary';
    }

    public function name(): string
    {
        return 'Freshdesk: Delete Ticket Summary';
    }

    public function description(): string
    {
        return 'Removes a ticket\'s summary.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
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

        return $this->destroy($run, $config, "/tickets/{$id}/summary", $id);
    }
}
