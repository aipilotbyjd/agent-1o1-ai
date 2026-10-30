<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZendeskPreviewMacroNode extends AbstractZendeskNode
{
    public function type(): string
    {
        return 'zendesk_preview_macro';
    }

    public function name(): string
    {
        return 'Zendesk: Preview Macro';
    }

    public function description(): string
    {
        return 'Shows what a macro would change on a ticket without changing it.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
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

        return ['ticket' => data_get($this->zendesk($run, $config, 'GET', "/tickets/{$id}/macros/{$macro}/apply"), 'result.ticket')];
    }
}
