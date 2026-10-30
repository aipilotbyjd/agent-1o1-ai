<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZendeskApplyMacroNode extends AbstractZendeskNode
{
    public function type(): string
    {
        return 'zendesk_apply_macro';
    }

    public function name(): string
    {
        return 'Zendesk: Apply Macro';
    }

    public function description(): string
    {
        return 'Runs a macro on a ticket and saves the result, including any comment it adds.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['ticket_id', 'macro_id'],
            'properties' => [
                ...$this->credentialFields(),
                'ticket_id' => Field::text('Ticket id'),
                'macro_id' => Field::dynamic('Macro', 'zendesk.macros'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'ticket_id', 'The ticket');
        $macro = $this->numericId($config, 'macro_id', 'The macro');
        $changes = (array) data_get($this->zendesk($run, $config, 'GET', "/tickets/{$id}/macros/{$macro}/apply"), 'result.ticket', []);
        unset($changes['id'], $changes['url']);

        return $this->shapeTicket((array) ($this->zendesk($run, $config, 'PUT', "/tickets/{$id}", [], ['ticket' => $changes])['ticket'] ?? []));
    }
}
