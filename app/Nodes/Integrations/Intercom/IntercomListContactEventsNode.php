<?php

namespace App\Nodes\Integrations\Intercom;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

class IntercomListContactEventsNode extends AbstractIntercomNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'intercom_list_contact_events';
    }

    public function name(): string
    {
        return 'Intercom: List Contact\'s Data Events';
    }

    public function description(): string
    {
        return 'Lists a contact\'s data events. As a trigger it starts the flow for each new event.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['user_id'],
            'properties' => [
                ...$this->credentialFields(),
                'user_id' => Field::text('External user id', 'The contact\'s external id, as it was sent with the events.'),
                'limit' => Field::integer('Max results', null, 50, 1, 500),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $rows = $this->cursorAll($run, $config, '/events', 'events', ['type' => 'user', 'user_id' => $this->requiredString($config, 'user_id', 'The user')], null, $this->limit($config, 50));

        return ['events' => $rows, 'count' => count($rows)];
    }

    protected function triggerItemsPath(): ?string
    {
        return 'events';
    }

    public function triggerItemId(array $item): string
    {
        return $item['id'] ?? (($item['event_name'] ?? '').':'.($item['created_at'] ?? ''));
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
        return 'Intercom: New event';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a contact records a new data event. Checked every few minutes.';
    }
}
