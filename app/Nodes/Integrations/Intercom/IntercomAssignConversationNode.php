<?php

namespace App\Nodes\Integrations\Intercom;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class IntercomAssignConversationNode extends AbstractIntercomNode
{
    public function type(): string
    {
        return 'intercom_assign_conversation';
    }

    public function name(): string
    {
        return 'Intercom: Assign Conversation';
    }

    public function description(): string
    {
        return 'Gives a conversation to a teammate or a team.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['conversation_id', 'assignee_id'],
            'properties' => [
                ...$this->credentialFields(),
                'conversation_id' => Field::text('Conversation id'),
                'assignee_type' => Field::select('Assign to', ['admin' => 'Teammate', 'team' => 'Team'], null, 'admin'),
                'assignee_id' => Field::text('Assignee id', 'A teammate\'s or team\'s id. 0 unassigns.'),
                'body' => Field::advanced(Field::text('Note')),
                'admin_id' => Field::advanced(Field::dynamic('As teammate', 'intercom.admins')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->safeId($config, 'conversation_id', 'The conversation');

        return $this->shapeConversation($this->intercom($run, $config, 'POST', "/conversations/{$id}/parts", [], $this->present(['message_type' => 'assignment', 'type' => ($config['assignee_type'] ?? 'admin') === 'team' ? 'team' : 'admin', 'admin_id' => $this->adminId($run, $config), 'assignee_id' => $this->safeId($config, 'assignee_id', 'The assignee'), 'body' => $this->text($config, 'body')])));
    }
}
