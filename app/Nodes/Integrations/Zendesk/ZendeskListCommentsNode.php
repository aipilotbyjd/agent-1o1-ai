<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

class ZendeskListCommentsNode extends AbstractZendeskNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'zendesk_list_comments';
    }

    public function name(): string
    {
        return 'Zendesk: List Comments';
    }

    public function description(): string
    {
        return 'Lists the comments of a ticket, newest first.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['ticket_id'],
            'properties' => [
                ...$this->credentialFields(),
                'ticket_id' => Field::text('Ticket id'),
                'limit' => Field::integer('Max results', null, 100, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'ticket_id', 'The ticket');

        return $this->listing($run, $config, "/tickets/{$id}/comments", 'comments', ['sort_order' => 'desc'], $this->shapeComment(...));
    }

    protected function triggerItemsPath(): ?string
    {
        return 'comments';
    }

    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'limit' => 500];
    }

    public function triggerListsNewestFirst(): bool
    {
        return true;
    }

    public function triggerName(): string
    {
        return 'Zendesk: New comment on a ticket';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow for each new comment on one ticket. Checked every few minutes.';
    }
}
