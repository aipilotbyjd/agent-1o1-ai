<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Nodes\Support\Field;

class ZendeskListTaggedUsersNode extends AbstractZendeskSearchTriggerNode
{
    public function type(): string
    {
        return 'zendesk_list_tagged_users';
    }

    public function name(): string
    {
        return 'Zendesk: List Tagged Users';
    }

    public function description(): string
    {
        return 'Lists people carrying a tag who changed lately. As a trigger it starts the flow once for each person who gets the tag. Instant, with a check every few minutes.';
    }

    protected function resource(): string
    {
        return 'user';
    }

    protected function stamp(): string
    {
        return 'updated';
    }

    protected function requiredFields(): array
    {
        return ['tag'];
    }

    protected function extraFields(): array
    {
        return ['tag' => Field::text('Tag', null, 'vip')];
    }

    protected function searchFilter(array $config): string
    {
        $tag = $this->requiredString($config, 'tag', 'The tag');

        return 'tags:'.(preg_replace('/[^\w:\/.-]/u', '', $tag) ?? '');
    }

    public function triggerItemId(array $item): string
    {
        return (string) ($item['id'] ?? '');
    }

    protected function triggerItemsPath(): ?string
    {
        return 'users';
    }

    public function triggerName(): string
    {
        return 'Zendesk: Tag added to a user';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow once when a person gets a tag. Instant, with a check every few minutes.';
    }
}
