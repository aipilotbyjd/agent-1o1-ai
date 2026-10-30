<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskUpdateAgentNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_update_agent';
    }

    public function name(): string
    {
        return 'Freshdesk: Update Agent';
    }

    public function description(): string
    {
        return 'Changes an agent. Only what is filled in changes.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['agent_id'],
            'properties' => [
                ...$this->credentialFields(),
                'agent_id' => Field::dynamic('Agent', 'freshdesk.agents'),
                'ticket_scope' => Field::advanced(Field::select('Ticket access', ['1' => 'Global', '2' => 'Group', '3' => 'Restricted'])),
                'occasional' => Field::advanced(Field::boolean('Occasional agent')),
                'group_ids' => Field::advanced(Field::text('Group ids', 'Separated by commas.')),
                'role_ids' => Field::advanced(Field::text('Role ids', 'Separated by commas.')),
                'skill_ids' => Field::advanced(Field::text('Skill ids', 'Separated by commas.')),
                'signature' => Field::advanced(Field::textarea('Signature')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'agent_id', 'The agent');
        $body = $this->present(['ticket_scope' => $this->number($config, 'ticket_scope'), 'occasional' => $this->flag($config, 'occasional'), 'group_ids' => $this->idList($config, 'group_ids', 'The groups', false), 'role_ids' => $this->idList($config, 'role_ids', 'The roles', false), 'skill_ids' => $this->idList($config, 'skill_ids', 'The skills', false), 'signature' => $this->text($config, 'signature')]);
        if ($body === []) {
            throw new \InvalidArgumentException('Fill in something to change.');
        }

        return $this->freshdesk($run, $config, 'PUT', "/agents/{$id}", [], $body);
    }
}
