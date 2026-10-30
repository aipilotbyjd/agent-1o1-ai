<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

class ZendeskListRecentActionsNode extends AbstractZendeskNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'zendesk_list_recent_actions';
    }

    public function name(): string
    {
        return 'Zendesk: List Recent Ticket Actions';
    }

    public function description(): string
    {
        return 'Lists what happened on tickets across the account lately, newest first. As a trigger it starts the flow for each action. Needs an admin.';
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
        $rows = $this->listAll($run, $config, '/ticket_audits', 'audits', ['page' => ['size' => 100]], $this->limit($config, 100));

        return ['audits' => $rows, 'count' => count($rows)];
    }

    protected function triggerItemsPath(): ?string
    {
        return 'audits';
    }

    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'limit' => 300];
    }

    public function triggerListsNewestFirst(): bool
    {
        return true;
    }

    public function triggerName(): string
    {
        return 'Zendesk: New action on a ticket';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow for every change made to any ticket. Needs an admin. Checked every few minutes.';
    }
}
