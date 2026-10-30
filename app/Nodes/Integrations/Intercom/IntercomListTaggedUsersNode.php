<?php

namespace App\Nodes\Integrations\Intercom;

use App\Nodes\Support\Field;

class IntercomListTaggedUsersNode extends AbstractIntercomSearchTriggerNode
{
    public function type(): string
    {
        return 'intercom_list_tagged_users';
    }

    public function name(): string
    {
        return 'Intercom: List Tagged Users';
    }

    public function description(): string
    {
        return 'Lists the users with a tag. As a trigger it starts the flow once for each user who gets it. Checked every few minutes.';
    }

    protected function resource(): string
    {
        return 'contacts';
    }

    protected function stampField(): ?string
    {
        return null;
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
        return [['field' => 'role', 'operator' => '=', 'value' => 'user'], ['field' => 'tag_id', 'operator' => '=', 'value' => $this->safeId($config, 'tag_id', 'The tag')]];
    }

    public function triggerName(): string
    {
        return 'Intercom: Tag added to user';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow once when a user gets a tag. Checked every few minutes.';
    }
}
