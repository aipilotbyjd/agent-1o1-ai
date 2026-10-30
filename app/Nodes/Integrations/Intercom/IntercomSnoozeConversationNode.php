<?php

namespace App\Nodes\Integrations\Intercom;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use Illuminate\Support\Carbon;

class IntercomSnoozeConversationNode extends AbstractIntercomNode
{
    public function type(): string
    {
        return 'intercom_snooze_conversation';
    }

    public function name(): string
    {
        return 'Intercom: Snooze Conversation';
    }

    public function description(): string
    {
        return 'Snoozes a conversation until a time.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['conversation_id', 'snoozed_until'],
            'properties' => [
                ...$this->credentialFields(),
                'conversation_id' => Field::text('Conversation id'),
                'snoozed_until' => Field::datetime('Snooze until'),
                'admin_id' => Field::advanced(Field::dynamic('As teammate', 'intercom.admins')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->managePart($run, $config, 'snoozed', ['snoozed_until' => Carbon::parse($this->requiredString($config, 'snoozed_until', 'The time'))->getTimestamp()]);
    }
}
