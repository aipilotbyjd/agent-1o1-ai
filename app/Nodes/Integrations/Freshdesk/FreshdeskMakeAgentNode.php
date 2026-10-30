<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskMakeAgentNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_make_agent';
    }

    public function name(): string
    {
        return 'Freshdesk: Make Contact An Agent';
    }

    public function description(): string
    {
        return 'Turns a contact into an agent.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['contact_id'],
            'properties' => [
                ...$this->credentialFields(),
                'contact_id' => Field::text('Contact id'),
                'occasional' => Field::advanced(Field::boolean('Occasional agent')),
                'ticket_scope' => Field::advanced(Field::select('Ticket access', ['1' => 'Global', '2' => 'Group', '3' => 'Restricted'])),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'contact_id', 'The contact');

        return $this->freshdesk($run, $config, 'PUT', "/contacts/{$id}/make_agent", [], $this->present(['occasional' => $this->flag($config, 'occasional'), 'ticket_scope' => $this->number($config, 'ticket_scope')]));
    }
}
