<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskGetPrimeAssociationNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_get_prime_association';
    }

    public function name(): string
    {
        return 'Freshdesk: Get Prime Association';
    }

    public function description(): string
    {
        return 'Reads the prime ticket of a linked ticket.';
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

        return $this->findOne($run, $config, "/tickets/{$id}/prime_association", $id, $this->shapeTicket(...));
    }
}
