<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

class ZendeskListSuspendedTicketsNode extends AbstractZendeskNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'zendesk_list_suspended_tickets';
    }

    public function name(): string
    {
        return 'Zendesk: List Suspended Tickets';
    }

    public function description(): string
    {
        return 'Lists tickets held back as suspected spam or from unverified senders.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
                'limit' => Field::integer('Max results', null, 100, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->listing($run, $config, '/suspended_tickets', 'suspended_tickets', ['sort_by' => 'created_at', 'sort_order' => 'desc']);
    }

    protected function triggerItemsPath(): ?string
    {
        return 'suspended_tickets';
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
        return 'Zendesk: New suspended ticket';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a message lands in suspended tickets. Checked every few minutes.';
    }
}
