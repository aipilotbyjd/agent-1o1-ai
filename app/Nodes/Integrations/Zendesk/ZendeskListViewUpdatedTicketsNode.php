<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

class ZendeskListViewUpdatedTicketsNode extends AbstractZendeskNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'zendesk_list_view_updated_tickets';
    }

    public function name(): string
    {
        return 'Zendesk: List Updated Tickets In View';
    }

    public function description(): string
    {
        return 'Lists the tickets in a view, latest change first. As a trigger it starts the flow for each change to a ticket in the view.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['view_id'],
            'properties' => [
                ...$this->credentialFields(),
                'view_id' => Field::dynamic('View', 'zendesk.views', null, 'Unassigned tickets'),
                'limit' => Field::integer('Max results', null, 100, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'view_id', 'The view');

        return $this->listing($run, $config, "/views/{$id}/tickets", 'tickets', ['sort_by' => 'updated_at', 'sort_order' => 'desc'], $this->shapeTicket(...));
    }

    protected function triggerItemsPath(): ?string
    {
        return 'tickets';
    }

    public function triggerItemId(array $item): string
    {
        return ($item['id'] ?? '').':'.($item['updated_at'] ?? '');
    }

    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'limit' => 1000];
    }

    public function triggerListsNewestFirst(): bool
    {
        return true;
    }

    public function triggerName(): string
    {
        return 'Zendesk: Updated ticket in view';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a ticket in a view changes. Checked every few minutes.';
    }
}
