<?php

namespace App\Nodes\Integrations\Intercom;

use App\Nodes\Support\Field;

class IntercomListTaggedConversationsNode extends AbstractIntercomSearchTriggerNode
{
    public function type(): string
    {
        return 'intercom_list_tagged_conversations';
    }

    public function name(): string
    {
        return 'Intercom: List Tagged Conversations';
    }

    public function description(): string
    {
        return 'Lists the conversations with a tag. As a trigger it starts the flow once for each conversation that gets it. Checked every few minutes.';
    }

    protected function resource(): string
    {
        return 'conversations';
    }

    protected function stampField(): ?string
    {
        return 'updated_at';
    }

    protected function requiredFields(): array
    {
        return ['tag_id'];
    }

    protected function extraFields(): array
    {
        return ['tag_id' => Field::dynamic('Tag', 'intercom.tags', null, 'VIP')];
    }

    protected function filters(array $config): array
    {
        return [['field' => 'tag_ids', 'operator' => '=', 'value' => $this->safeId($config, 'tag_id', 'The tag')]];
    }

    public function triggerName(): string
    {
        return 'Intercom: Tag added to conversation';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow once when a conversation gets a tag. Checked every few minutes.';
    }
}
