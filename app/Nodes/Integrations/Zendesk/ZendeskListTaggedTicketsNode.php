<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Nodes\Support\Field;

class ZendeskListTaggedTicketsNode extends AbstractZendeskSearchTriggerNode
{
    public function type(): string
    {
        return 'zendesk_list_tagged_tickets';
    }

    public function name(): string
    {
        return 'Zendesk: List Tagged Tickets';
    }

    public function description(): string
    {
        return 'Lists tickets carrying a tag that changed lately. As a trigger it starts the flow once for each ticket that gets the tag. Instant, with a check every few minutes.';
    }

    protected function resource(): string
    {
        return 'ticket';
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
        return 'tickets';
    }

    public function triggerName(): string
    {
        return 'Zendesk: New tag on a ticket';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow once when a ticket gets a tag. Instant, with a check every few minutes.';
    }
}
