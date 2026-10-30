<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskCreateAgentNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_create_agent';
    }

    public function name(): string
    {
        return 'Freshdesk: Create Agent';
    }

    public function description(): string
    {
        return 'Adds an agent to the account.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['email', 'ticket_scope'],
            'properties' => [
                ...$this->credentialFields(),
                'email' => Field::text('Email', null, 'ada@example.com'),
                'name' => Field::text('Name'),
                'ticket_scope' => Field::select('Ticket access', ['1' => 'Global', '2' => 'Group', '3' => 'Restricted'], null, '1'),
                'occasional' => Field::advanced(Field::boolean('Occasional agent')),
                'group_ids' => Field::advanced(Field::text('Group ids', 'Separated by commas.')),
                'role_ids' => Field::advanced(Field::text('Role ids', 'Separated by commas.')),
                'skill_ids' => Field::advanced(Field::text('Skill ids', 'Separated by commas.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->freshdesk($run, $config, 'POST', '/agents', [], $this->present(['email' => $this->email($config), 'name' => $this->text($config, 'name'), 'ticket_scope' => $this->number($config, 'ticket_scope') ?? 1, 'occasional' => $this->flag($config, 'occasional') ?? false, 'group_ids' => $this->idList($config, 'group_ids', 'The groups', false), 'role_ids' => $this->idList($config, 'role_ids', 'The roles', false), 'skill_ids' => $this->idList($config, 'skill_ids', 'The skills', false)]));
    }
}
