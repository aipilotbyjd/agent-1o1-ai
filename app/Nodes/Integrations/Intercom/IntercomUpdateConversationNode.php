<?php

namespace App\Nodes\Integrations\Intercom;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class IntercomUpdateConversationNode extends AbstractIntercomNode
{
    public function type(): string
    {
        return 'intercom_update_conversation';
    }

    public function name(): string
    {
        return 'Intercom: Update Conversation';
    }

    public function description(): string
    {
        return 'Sets a conversation\'s title, custom attributes or read state.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['conversation_id'],
            'properties' => [
                ...$this->credentialFields(),
                'conversation_id' => Field::text('Conversation id'),
                'title' => Field::advanced(Field::text('Title')),
                'read' => Field::advanced(Field::boolean('Mark as read')),
                'company_id' => Field::advanced(Field::text('Company id')),
                'custom_attributes' => Field::advanced(Field::json('Custom attributes (JSON)', 'e.g. {"issue": "billing"}.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->safeId($config, 'conversation_id', 'The conversation');
        $body = $this->present(['title' => $this->text($config, 'title'), 'read' => $this->flag($config, 'read'), 'company_id' => $this->text($config, 'company_id'), 'custom_attributes' => $this->jsonObject($config, 'custom_attributes', 'The custom attributes') ?: null]);
        if ($body === []) {
            throw new \InvalidArgumentException('Fill in something to change.');
        }

        return $this->shapeConversation($this->intercom($run, $config, 'PUT', "/conversations/{$id}", [], $body));
    }
}
